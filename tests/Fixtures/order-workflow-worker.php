<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Simon\ClientesAdministracion\Application\ManageCurrentAccounts;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Application\RegisterOrder;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.url' => null, 'inventory.audit_key' => str_repeat('test-only-concurrent-order-', 3)]);
DB::purge('sqlite');
$request = json_decode(base64_decode($argv[3]), true, flags: JSON_THROW_ON_ERROR);
echo "READY\n";
$deadline = microtime(true) + 20;

while (! is_file($argv[2])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('No se abrió la barrera de la prueba concurrente.');
    }

    usleep(1000);
}

try {
    $id = match ($request['action']) {
        'finance' => app(ManageCurrentAccounts::class)->post($request['actor_id'], $request['token'], $request['customer_id'], 'payment', $request['amount'], 'Cobro concurrente de prueba'),
        'pick' => app(ManagePicking::class)->scan($request['actor_id'], $request['token'], $request['line_id'], $request['code']),
        'register' => app(RegisterOrder::class)->handle($request['actor_id'], $request['token'], $request['customer_id'], 'F', $request['lines']),
        'dispatch' => app(MoveStock::class)->dispatch($request['actor_id'], $request['token'], $request['line_id'], $request['batch_id'], 4, true),
        'cancel' => app(ManageOrders::class)->transition($request['actor_id'], $request['token'], $request['order_id'], 0, 'cancel', 'Cancelación concurrente'),
    };
    echo json_encode(['accepted' => true, 'id' => $id], JSON_THROW_ON_ERROR);
} catch (BusinessRuleViolation $exception) {
    echo json_encode(['accepted' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
