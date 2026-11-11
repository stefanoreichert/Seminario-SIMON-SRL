<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageCurrentAccounts;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\VentasPedidos\Application\RegisterOrder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
{
    private string $databaseFile;

    private string $barrierFile;

    private User $admin;

    private int $customerId;

    private int $variantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'simon-order-test-');
        $this->barrierFile = $this->databaseFile.'.ready';
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databaseFile, 'database.connections.sqlite.url' => null, 'inventory.audit_key' => str_repeat('test-only-concurrent-order-', 3)]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->customerId = app(ManageCustomers::class)->register($this->admin->id, 'Cliente concurrente', '20-12345678-9', 'Manual');
        $this->variantId = app(PrepareInventory::class)->variant($this->admin->id, 'CON-01', 'Botín', 'Negro', '40');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();

        foreach ([$this->databaseFile, $this->databaseFile.'-wal', $this->databaseFile.'-shm', $this->barrierFile] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /** @return array<string,mixed> */
    private function registration(int $quantity = 10, ?string $token = null): array
    {
        return ['action' => 'register', 'actor_id' => $this->admin->id, 'token' => $token ?? (string) Str::uuid(), 'customer_id' => $this->customerId, 'lines' => [['variant_id' => $this->variantId, 'quantity' => $quantity]]];
    }

    /**
     * @param  list<array<string,mixed>>  $requests
     * @return list<array{accepted:bool,id?:?int,message?:string}>
     */
    private function concurrently(array $requests): array
    {
        $workers = array_map(fn (array $request): Process => new Process([
            PHP_BINARY, base_path('tests/Fixtures/order-workflow-worker.php'), $this->databaseFile,
            $this->barrierFile, base64_encode(json_encode($request, JSON_THROW_ON_ERROR)),
        ], base_path(), ['APP_ENV' => 'testing'], timeout: 40), $requests);

        try {
            foreach ($workers as $worker) {
                $worker->start();
            }

            foreach ($workers as $worker) {
                $this->assertTrue($worker->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'READY')), $worker->getErrorOutput());
            }

            touch($this->barrierFile);
            $results = [];

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode(trim(str_replace("READY\n", '', $worker->getOutput())), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    public function test_simultaneous_retries_return_one_order_and_number(): void
    {
        $request = $this->registration();
        $results = $this->concurrently([$request, $request]);
        $this->assertSame([true, true], array_column($results, 'accepted'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 1);
    }

    public function test_two_posts_with_distinct_tokens_cannot_create_identical_active_orders(): void
    {
        $results = $this->concurrently([$this->registration(), $this->registration()]);
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['accepted'])));
        $this->assertDatabaseCount('business_orders', 1);
    }

    public function test_simultaneous_different_orders_receive_unique_global_numbers(): void
    {
        $results = $this->concurrently([$this->registration(10), $this->registration(11)]);
        $this->assertSame([true, true], array_column($results, 'accepted'));
        $this->assertNotSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(['NP-000001', 'NP-000002'], DB::table('business_orders')->orderBy('id')->pluck('number')->all());
    }

    public function test_dispatch_and_stale_cancellation_cannot_both_commit(): void
    {
        $request = $this->registration();
        $id = app(RegisterOrder::class)->handle($this->admin->id, $request['token'], $this->customerId, 'F', $request['lines']);
        app(ManageCustomers::class)->authorize($this->admin->id, $id, true, true, 'Autorización concurrente');
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variantId, 'LOTE-CON', '2026-01-01', 20);
        $results = $this->concurrently([
            ['action' => 'dispatch', 'actor_id' => $this->admin->id, 'token' => (string) Str::uuid(), 'line_id' => DB::table('business_order_lines')->value('id'), 'batch_id' => DB::table('inventory_batches')->value('id')],
            ['action' => 'cancel', 'actor_id' => $this->admin->id, 'token' => (string) Str::uuid(), 'order_id' => $id],
        ]);
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['accepted'])));
        $dispatched = $results[0]['accepted'];
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'revision' => 1, 'status' => $dispatched ? 'Parcial' : 'Anulado']);
        $this->assertDatabaseHas('inventory_batches', ['quantity' => $dispatched ? 16 : 20]);
        $this->assertDatabaseHas('business_order_lines', ['dispatched' => $dispatched ? 4 : 0]);
        $this->assertSame($dispatched ? 2 : 1, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_competing_scans_cannot_pick_more_pairs_than_the_pending_line(): void
    {
        $request = $this->registration(1);
        $id = app(RegisterOrder::class)->handle($this->admin->id, $request['token'], $this->customerId, 'F', $request['lines']);
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variantId, 'LOTE-PICKING', '2026-01-01', 2);
        $batch = (int) DB::table('inventory_batches')->value('id');
        $line = (int) DB::table('business_order_lines')->where('order_id', $id)->value('id');
        app(ManagePicking::class)->identify($this->admin->id, (string) Str::uuid(), $batch);
        $codes = DB::table('inventory_pair_units')->pluck('code')->all();
        $results = $this->concurrently(array_map(fn (string $code): array => ['action' => 'pick', 'actor_id' => $this->admin->id, 'token' => (string) Str::uuid(), 'line_id' => $line, 'code' => $code], $codes));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['accepted'])));
        $this->assertSame(1, DB::table('inventory_pair_units')->where('state', 'picked')->count());
        $this->assertDatabaseHas('inventory_batches', ['id' => $batch, 'quantity' => 2]);
        $this->assertDatabaseHas('business_order_lines', ['id' => $line, 'dispatched' => 0]);
    }

    public function test_parallel_payments_keep_both_financial_movements_without_touching_inventory(): void
    {
        app(ManageCurrentAccounts::class)->post($this->admin->id, (string) Str::uuid(), $this->customerId, 'opening', '100', 'Saldo inicial de prueba');
        $head = (array) DB::table('inventory_audit_head')->first();
        $results = $this->concurrently(array_map(fn (string $amount): array => ['action' => 'finance', 'actor_id' => $this->admin->id, 'token' => (string) Str::uuid(), 'customer_id' => $this->customerId, 'amount' => $amount], ['10', '20']));
        $this->assertSame([true, true], array_column($results, 'accepted'));
        $report = app(ManageCurrentAccounts::class)->overview($this->admin->id, $this->customerId);
        $this->assertSame(7000, $report['account']['balance_minor']);
        $this->assertSame(3, $report['entries']['total']);
        $this->assertSame($head, (array) DB::table('inventory_audit_head')->first());
    }
}
