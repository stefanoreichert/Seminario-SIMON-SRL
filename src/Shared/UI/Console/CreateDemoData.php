<?php

namespace Simon\Shared\UI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Domain\Permissions;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCurrentAccounts;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\InventarioTrazabilidad\Application\ManageSupplies;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\RegisterOrder;
use Throwable;

final class CreateDemoData extends Command
{
    protected $signature = 'demo:create {--actor= : Correo de un administrador activo existente}';

    protected $description = 'Agregar un recorrido DEMO conectado, sin reemplazar datos existentes ni duplicar la muestra';

    public function handle(AccessPolicy $access): int
    {
        $administrator = DB::table('users')->where('email', $this->option('actor'))->where('is_active', true)->first();
        if ($administrator === null || ! $access->allows($administrator->id, 'access.manage')) {
            $this->error('Indicá --actor con el correo de un administrador activo de permisos.');

            return self::FAILURE;
        }

        foreach (['inventory_pair_units', 'inventory_supply_lots'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('Activá primero el esquema de picking e insumos. No se modificaron datos.');

                return self::FAILURE;
            }
        }

        try {
            $result = DB::transaction(fn (): array => $this->create($administrator->id));
        } catch (Throwable $exception) {
            $this->error('No se completó la muestra; la transacción fue revertida: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Muestra DEMO lista. Sus datos son ficticios y no tienen validez comercial.');
        $this->table(['Ejemplo', 'Referencia'], [
            ['Cliente', 'DEMO · Calzados del Sur (#'.$result['customer'].')'],
            ['Artículo', 'DEMO-BOTIN-NEGRO-40'],
            ['Pedido', $result['number'].' · línea #'.$result['line']],
            ['Producción', 'DEMO-FABRICA-001 · tanda #'.$result['production']],
            ['Picking', route('picking.index', ['line_id' => $result['line'], 'batch_id' => $result['batch']])],
            ['Trazabilidad', route('traceability.index', ['lot' => 'DEMO-FABRICA-001'])],
            ['Cuenta corriente', $result['finance'] ? route('current-accounts.index', ['customer_id' => $result['customer']]) : 'Pendiente: esquema financiero todavía no activado'],
            ['Usuario', 'demo.operacion@example.test'],
            ['Rol', 'DEMO · Operación (sin administración de usuarios)'],
        ]);
        if ($result['password'] !== null) {
            $this->line('Contraseña inicial de la cuenta DEMO: '.$result['password']);
        } else {
            $this->line('La cuenta DEMO ya existía: se conservó su contraseña.');
        }
        if (! $result['finance']) {
            $this->warn('La muestra operativa está creada. Volvé a ejecutar este comando tras activar cuenta corriente para agregar sus ejemplos.');
        }

        return self::SUCCESS;
    }

    /** @return array{customer:int,number:string,line:int,production:int,batch:int,finance:bool,password:?string} */
    private function create(int $administrator): array
    {
        $access = app(ManageAccess::class);
        $role = DB::table('access_roles')->where('name', 'DEMO · Operación')->value('id');
        $role ??= $access->saveRole($administrator, null, 'DEMO · Operación', array_values(array_diff(array_keys(Permissions::all()), ['access.manage'])));
        $user = DB::table('users')->where('email', 'demo.operacion@example.test')->first();
        $password = null;
        if ($user === null) {
            $password = Str::random(24);
            $actor = $access->saveUser($administrator, null, 'DEMO · Operador de presentación', 'demo.operacion@example.test', $password, null, true, [(int) $role]);
        } else {
            $this->assertDemo($user->name);
            $actor = (int) $user->id;
        }

        $customers = app(ManageCustomers::class);
        $customer = DB::table('business_customers')->where('cuit', '20999000123')->first();
        if ($customer !== null) {
            $this->assertDemo($customer->name);
        }
        $customerId = $customer === null ? $customers->register($actor, 'DEMO · Calzados del Sur', '20999000123', 'DEMO · Evaluación ficticia para presentación. Sin validez comercial.', '000-000-0000', 'cliente.demo@example.test') : (int) $customer->id;
        $address = DB::table('customer_delivery_addresses')->where('customer_id', $customerId)->where('label', 'DEMO · Sucursal central')->value('id');
        $address ??= app(ManageAddresses::class)->save($actor, $customerId, null, 'DEMO · Sucursal central', 'Calle de ejemplo 123', 'Posadas', 'Misiones', '3300', 'Argentina', 'DEMO · Destino ficticio; no utilizar para entregas reales.');

        $inventory = app(PrepareInventory::class);
        $variant = DB::table('catalog_variants')->where('code', 'DEMO-BOTIN-NEGRO-40')->first();
        if ($variant !== null) {
            $this->assertDemo($variant->model);
        }
        $variantId = $variant === null ? $inventory->variant($actor, 'DEMO-BOTIN-NEGRO-40', 'DEMO · Botín de trabajo', 'Negro', '40') : (int) $variant->id;
        $production = DB::table('production_batches')->where('registration_id', $this->operation(1))->first();
        $date = $production?->manufactured_on ?? now()->toDateString();
        $productionId = $production === null ? $inventory->production($actor, $this->operation(1), $variantId, 'DEMO-FABRICA-001', $date, 12) : (int) $production->id;
        $orderId = app(RegisterOrder::class)->handle($actor, $this->operation(2), $customerId, 'F', [['variant_id' => $variantId, 'quantity' => 20]], (int) $address);
        $lineId = (int) DB::table('business_order_lines')->where('order_id', $orderId)->where('variant_id', $variantId)->value('id');

        $movements = app(MoveStock::class);
        $movements->opening($actor, $this->operation(3), $variantId, 'DEMO-INICIAL-001', $date, 5);
        $receipt = $movements->receive($actor, $this->operation(4), $productionId, 8);
        $batchId = (int) DB::table('inventory_movements')->where('id', $receipt)->value('batch_id');
        if (! DB::table('inventory_movements')->where('operation_id', $this->operation(8))->exists()) {
            $customers->authorize($actor, $orderId, true, true, 'DEMO · Autorización ficticia para presentación; no equivale a facturación ni cobro.');
            $picking = app(ManagePicking::class);
            $picking->identify($actor, $this->operation(5), $batchId);
            $codes = DB::table('inventory_pair_units')->where('batch_id', $batchId)->orderBy('code')->limit(2)->pluck('code')->all();
            $picking->scan($actor, $this->operation(6), $lineId, $codes[0]);
            $movements->dispatch($actor, $this->operation(8), $lineId, $batchId, 1, pairCodes: [$codes[0]]);
            $picking->scan($actor, $this->operation(7), $lineId, $codes[1]);
        }

        $supply = DB::table('inventory_supply_lots')->where('operation_id', $this->operation(9))->first();
        $expiresOn = $supply?->expires_on ?? now()->subDay()->toDateString();
        app(ManageSupplies::class)->register($actor, $this->operation(9), 'DEMO · Adhesivo de muestra', 'DEMO-INSUMO-001', $expiresOn, $productionId);
        $finance = Schema::hasTable('customer_current_account_entries');
        if ($finance) {
            foreach ([['opening', '100000', 'Saldo inicial ficticio'], ['charge', '25000', 'Cargo ficticio'], ['payment', '10000', 'Cobro ficticio'], ['adjustment', '-500', 'Ajuste ficticio documentado'], ['reconciliation', '114500', 'Conciliación ficticia']] as $index => [$kind, $amount, $note]) {
                app(ManageCurrentAccounts::class)->post($actor, $this->operation(20 + $index), $customerId, $kind, $amount, 'DEMO · Fuente de presentación sin validez comercial', 'DEMO · '.$note);
            }
        }

        return ['customer' => $customerId, 'number' => (string) DB::table('business_orders')->where('id', $orderId)->value('number'), 'line' => $lineId, 'production' => $productionId, 'batch' => $batchId, 'finance' => $finance, 'password' => $password];
    }

    private function operation(int $step): string
    {
        return sprintf('f40c2700-0000-4000-8000-%012d', $step);
    }

    private function assertDemo(string $name): void
    {
        if (! str_starts_with($name, 'DEMO · ')) {
            throw new BusinessRuleViolation('Una referencia DEMO coincide con un registro ajeno. No se reemplazó.');
        }
    }
}
