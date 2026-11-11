<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\InventarioTrazabilidad\Application\ManageSupplies;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Application\RegisterOrder;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');

if (! $application->environment('testing') || ! str_contains($database, 'simon-pending-browser-') || realpath(dirname($database)) === false) {
    throw new RuntimeException('Esta fixture solo admite una base temporal propia del ensayo de navegador.');
}

if ($argv[1] === 'setup') {
    $setupOptions = isset($argv[2]) ? json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR) : [];
    $orderCount = $setupOptions['orders'] ?? 1000;

    if (! is_int($orderCount) || $orderCount < 1 || $orderCount > 1000) {
        throw new RuntimeException('Volumen de la fixture fuera del rango permitido.');
    }

    Artisan::call('migrate', ['--force' => true]);
    $admin = User::factory()->create(['name' => 'Administración de prueba', 'email' => 'admin@rf008.example.test']);
    Artisan::call('access:bootstrap', ['email' => $admin->email]);
    $actors = ['admin' => $admin];

    foreach (['warehouse' => ['Depósito de prueba', ['inventory.view']], 'production' => ['Producción de prueba', ['production.manage']], 'S' => ['Vendedor S de prueba', ['orders.create']], 'G' => ['Vendedor G de prueba', ['orders.create']]] as $key => [$name, $permissions]) {
        $user = User::factory()->create(['name' => $name, 'sales_identifier' => in_array($key, ['S', 'G'], true) ? $key : null]);
        $role = DB::table('access_roles')->insertGetId(['name' => $name]);

        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
        }

        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
        $actors[$key] = $user;
    }

    $customers = [];
    $variants = [];

    for ($index = 0; $index < 25; $index++) {
        $customers[] = app(ManageCustomers::class)->register($admin->id, 'Cliente sintético '.($index + 1), (string) (30000000000 + $index), 'Crédito sintético privado');
    }

    for ($index = 0; $index < 5; $index++) {
        $variants[] = app(PrepareInventory::class)->variant($admin->id, 'ENSAYO-'.($index + 1), 'Botín de prueba', 'Negro', (string) (38 + $index));
    }

    $totals = ['admin' => 0, 'warehouse' => 0, 'production' => 0, 'S' => 0, 'G' => 0];
    $target = null;
    DB::transaction(function () use ($actors, $customers, $variants, $orderCount, &$totals, &$target): void {
        for ($index = 0; $index < $orderCount; $index++) {
            $origin = $index % 2 === 0 ? 'S' : 'G';
            $quantity = 100 + $index;
            $id = DB::table('business_orders')->insertGetId([
                'registration_id' => (string) Str::uuid(), 'request_digest' => hash('sha256', 'fixture-'.$index), 'number' => 'NP-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => $customers[$index % 25], 'seller_id' => $actors[$origin]->id, 'origin' => $origin, 'status' => 'Confirmado', 'created_at' => now(),
            ]);

            foreach ($variants as $variant) {
                DB::table('business_order_lines')->insert(['order_id' => $id, 'variant_id' => $variant, 'quantity' => $quantity, 'dispatched' => 0]);
            }

            $totals[$origin] += 5 * $quantity;

            if ($index === 0) {
                $target = ['order_id' => $id, 'line_id' => DB::table('business_order_lines')->where('order_id', $id)->value('id')];
            }
        }
    });
    $totals['admin'] = $totals['warehouse'] = $totals['production'] = $totals['S'] + $totals['G'];
    app(ManageCustomers::class)->authorize($admin->id, $target['order_id'], true, true, 'Ensayo sintético');
    app(MoveStock::class)->opening($admin->id, (string) Str::uuid(), $variants[0], 'LOTE-ENSAYO', '2026-01-01', 10000);
    $target['batch_id'] = DB::table('inventory_batches')->value('id');
    $target['draft_id'] = app(RegisterOrder::class)->handle($actors['S']->id, (string) Str::uuid(), $customers[0], 'S', [['variant_id' => $variants[0], 'quantity' => 21]], null, true);
    $sessions = [];

    foreach ($actors as $key => $actor) {
        $session = app('session')->driver();
        $session->flush();
        $session->setId(Str::random(40));
        $session->start();
        Auth::guard()->forgetUser();
        Auth::loginUsingId($actor->id);
        $session->save();
        $name = config('session.cookie');
        $sessions[$key] = ['id' => $actor->id, 'cookie' => app('encrypter')->encrypt(CookieValuePrefix::create($name, app('encrypter')->getKey()).$session->getId(), EncryptCookies::serialized($name))];
    }

    echo json_encode(['actors' => $sessions, 'cookie_name' => config('session.cookie'), 'totals' => $totals, 'target' => $target, 'customer_id' => $customers[0], 'variant_id' => $variants[0], 'orders' => $orderCount, 'lines' => $orderCount * 5], JSON_THROW_ON_ERROR);
    exit;
}

$request = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
$result = [];

switch ($request['action']) {
    case 'prepare-ui':
        $result['address_id'] = app(ManageAddresses::class)->save($request['actor_id'], $request['customer_id'], null, 'Dirección de prueba UI', 'Calle de ensayo 123', 'Posadas', 'Misiones');
        break;
    case 'prepare-picking':
        app(ManagePicking::class)->identify($request['actor_id'], $request['token'], $request['batch_id']);
        $result['codes'] = DB::table('inventory_pair_units')->where('batch_id', $request['batch_id'])->orderBy('code')->limit(3)->pluck('code')->all();
        app(ManageSupplies::class)->configure($request['actor_id'], 30);
        app(ManageSupplies::class)->register($request['actor_id'], (string) Str::uuid(), 'Adhesivo de prueba', 'INSUMO-VENCIDO', now()->subDay()->toDateString());
        app(ManageSupplies::class)->register($request['actor_id'], (string) Str::uuid(), 'Insumo de prueba', 'INSUMO-PROXIMO', now()->addDays(10)->toDateString());
        break;
    case 'production':
        $result['production_id'] = app(PrepareInventory::class)->production($request['actor_id'], $request['token'], $request['variant_id'], 'TANDA-RF010', '2026-01-01', $request['quantity']);
        break;
    case 'receive':
        app(MoveStock::class)->receive($request['actor_id'], $request['token'], $request['production_id'], $request['quantity']);
        break;
    case 'revoke':
        DB::table('access_user_roles')->where('user_id', $request['actor_id'])->delete();
        break;
    case 'create':
        app(RegisterOrder::class)->handle($request['actor_id'], $request['token'], $request['customer_id'], $request['origin'], [['variant_id' => $request['variant_id'], 'quantity' => $request['quantity']]]);
        break;
    case 'dispatch':
        app(MoveStock::class)->dispatch($request['actor_id'], $request['token'], $request['line_id'], $request['batch_id'], 4, true);
        break;
    case 'cancel':
        app(ManageOrders::class)->transition($request['actor_id'], $request['token'], $request['order_id'], 1, 'cancel', 'Anular saldo sintético');
        break;
    case 'confirm':
        app(ManageOrders::class)->transition($request['actor_id'], $request['token'], $request['draft_id'], 0, 'confirm');
        break;
    default:
        throw new RuntimeException('Acción del ensayo desconocida.');
}

echo json_encode([...$result, 'committedAtMs' => microtime(true) * 1000], JSON_THROW_ON_ERROR);
