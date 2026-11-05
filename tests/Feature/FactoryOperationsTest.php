<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class FactoryOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouse;

    private User $seller;

    private int $variantId;

    private int $customerId;

    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.audit_key' => str_repeat('test-only-audit-signing-key-', 2)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->warehouse = User::factory()->create();
        $this->seller = User::factory()->create(['sales_identifier' => 'S']);
        $this->grant($this->warehouse, ['inventory.view', 'inventory.receive', 'inventory.dispatch']);
        $this->grant($this->seller, ['orders.create']);
        $this->variantId = app(PrepareInventory::class)->variant($this->admin->id, 'TEST-01', 'Botín', 'Negro', '40');
        $this->customerId = app(ManageCustomers::class)->register($this->admin->id, 'Cliente de prueba', '20-12345678-9', 'Saldo de cuenta corriente: pendiente de cobro');
        $this->addressId = app(ManageAddresses::class)->save($this->admin->id, $this->customerId, null, 'Depósito de prueba', 'Calle de prueba 123', 'Posadas', 'Misiones');
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): int
    {
        $roleId = DB::table('access_roles')->insertGetId(['name' => 'Rol de prueba '.Str::uuid()]);

        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }

        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);

        return $roleId;
    }

    private function token(): string
    {
        return (string) Str::uuid();
    }

    private function production(int $quantity = 10, string $lot = 'LOTE-COMPARTIDO'): int
    {
        return app(PrepareInventory::class)->production($this->admin->id, $this->token(), $this->variantId, $lot, '2026-01-01', $quantity);
    }

    private function order(int $quantity = 10): int
    {
        $id = app(RegisterOrder::class)->handle($this->seller->id, $this->token(), $this->customerId, 'S', [['variant_id' => $this->variantId, 'quantity' => $quantity]], $this->addressId);

        return DB::table('business_order_lines')->where('order_id', $id)->value('id');
    }

    private function authorize(int $lineId, bool $billing = true, bool $credit = true): void
    {
        $orderId = DB::table('business_order_lines')->where('id', $lineId)->value('order_id');
        app(ManageCustomers::class)->authorize($this->admin->id, $orderId, $billing, $credit, 'Puede despacharse a cuenta corriente; no implica cobro.');
    }

    private function receive(int $quantity = 10): int
    {
        app(MoveStock::class)->receive($this->warehouse->id, $this->token(), $this->production($quantity), $quantity);

        return DB::table('inventory_batches')->where('variant_id', $this->variantId)->value('id');
    }

    public function test_receipt_reduces_work_in_process_and_is_idempotent(): void
    {
        $production = $this->production();
        $token = $this->token();
        $first = app(MoveStock::class)->receive($this->warehouse->id, $token, $production, 6);
        $second = app(MoveStock::class)->receive($this->warehouse->id, $token, $production, 6);

        $this->assertSame($first, $second);
        $this->assertDatabaseHas('production_batches', ['id' => $production, 'in_process' => 4]);
        $this->assertDatabaseHas('inventory_batches', ['lot' => 'LOTE-COMPARTIDO', 'quantity' => 6]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $entry = json_decode(DB::table('inventory_audits')->value('payload'), true);
        $this->assertSame($this->warehouse->id, $entry['actor_id']);
        $this->assertSame(6, $entry['quantity']);
        $this->assertNotEmpty($entry['occurred_at']);
        $this->assertSame('2026-01-01', $entry['batch']['manufactured_on']);
        $this->assertSame(1, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_partial_and_final_dispatch_update_merchandise_without_changing_credit_history(): void
    {
        $batchId = $this->receive();
        $lineId = $this->order();
        $this->authorize($lineId);
        $before = DB::table('business_customers')->where('id', $this->customerId)->value('credit_history');
        $token = $this->token();

        app(MoveStock::class)->dispatch($this->warehouse->id, $token, $lineId, $batchId, 4);
        app(MoveStock::class)->dispatch($this->warehouse->id, $token, $lineId, $batchId, 4);
        $this->assertDatabaseHas('business_order_lines', ['id' => $lineId, 'dispatched' => 4]);
        $this->assertDatabaseHas('business_orders', ['status' => 'Parcial']);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 6]);

        app(MoveStock::class)->dispatch($this->warehouse->id, $this->token(), $lineId, $batchId, 6, true);
        $this->assertDatabaseHas('business_orders', ['status' => 'Entregado']);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 0]);
        $this->assertDatabaseHas('inventory_movements', ['kind' => 'owner_withdrawal', 'quantity' => 6]);
        $this->assertSame($before, DB::table('business_customers')->where('id', $this->customerId)->value('credit_history'));
        $entry = json_decode(DB::table('inventory_audits')->orderByDesc('sequence')->value('payload'), true);
        $this->assertSame($this->admin->id, $entry['authorization']['authorized_by']);
        $this->assertStringContainsString('cuenta corriente', $entry['authorization']['conditions']);
        $this->assertSame(3, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_dispatch_rejects_missing_or_incomplete_administrative_validation(): void
    {
        $batchId = $this->receive();
        $lineId = $this->order();

        foreach ([null, [false, true], [true, false]] as $validation) {
            if ($validation !== null) {
                $this->authorize($lineId, ...$validation);
            }

            try {
                app(MoveStock::class)->dispatch($this->warehouse->id, $this->token(), $lineId, $batchId, 1);
                $this->fail('Un despacho sin validación completa no debe continuar.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 10]);
                $this->assertDatabaseHas('business_order_lines', ['id' => $lineId, 'dispatched' => 0]);
            }
        }

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_insufficient_stock_rolls_back_the_order_and_audit(): void
    {
        $batchId = $this->receive(2);
        $lineId = $this->order(10);
        $this->authorize($lineId);

        try {
            app(MoveStock::class)->dispatch($this->warehouse->id, $this->token(), $lineId, $batchId, 3);
            $this->fail('La salida no debe superar el stock.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_order_lines', ['id' => $lineId, 'dispatched' => 0]);
            $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 2]);
            $this->assertSame(1, app(SqlInventory::class)->verify()['sequence']);
        }
    }

    public function test_competing_dispatches_cannot_exceed_the_pending_quantity(): void
    {
        $batchId = $this->receive(20);
        $lineId = $this->order(10);
        $this->authorize($lineId);
        app(MoveStock::class)->dispatch($this->warehouse->id, $this->token(), $lineId, $batchId, 7);

        try {
            app(MoveStock::class)->dispatch($this->warehouse->id, $this->token(), $lineId, $batchId, 7);
            $this->fail('No se puede despachar dos veces el mismo saldo.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_order_lines', ['id' => $lineId, 'dispatched' => 7]);
            $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 13]);
            $this->assertSame(2, app(SqlInventory::class)->verify()['sequence']);
        }
    }

    public function test_audit_failure_rolls_back_receipt_and_production(): void
    {
        $production = $this->production();
        config(['inventory.audit_key' => null]);

        try {
            app(MoveStock::class)->receive($this->warehouse->id, $this->token(), $production, 5);
            $this->fail('Sin auditoría no se puede confirmar un alta.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('production_batches', ['id' => $production, 'in_process' => 10]);
            $this->assertDatabaseCount('inventory_batches', 0);
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    public function test_reusing_an_operation_id_with_different_content_is_rejected(): void
    {
        $production = $this->production();
        $token = $this->token();
        app(MoveStock::class)->receive($this->warehouse->id, $token, $production, 2);
        $this->expectException(BusinessRuleViolation::class);
        app(MoveStock::class)->receive($this->warehouse->id, $token, $production, 3);
    }

    public function test_sql_protection_blocks_changes_and_deletion_of_the_history(): void
    {
        $this->receive();

        foreach (['inventory_audits', 'inventory_movements'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    if ($operation === 'delete') {
                        DB::table($table)->delete();
                    } else {
                        DB::table($table)->update($table === 'inventory_audits' ? ['payload' => '{}'] : ['quantity' => 999]);
                    }

                    $this->fail('El historial debe permanecer inmutable.');
                } catch (QueryException $exception) {
                    $this->assertStringContainsString('inmutable', $exception->getMessage());
                }
            }
        }

        $this->assertSame(1, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_verification_detects_tampering_after_a_privileged_user_removes_a_trigger(): void
    {
        $this->receive();
        DB::unprepared('DROP TRIGGER inventory_audits_no_update');
        DB::table('inventory_audits')->update(['payload' => '{}']);
        $this->artisan('inventory:audit-verify')->assertFailed();
    }

    public function test_permissions_are_configurable_and_the_last_administrator_is_protected(): void
    {
        $manager = app(ManageAccess::class);
        $roleId = $manager->saveRole($this->admin->id, null, 'Carga de catálogo', ['catalog.manage']);
        $id = $manager->saveUser($this->admin->id, null, 'Persona de prueba', 'person@example.test', 'long-test-password', 'G', true, [$roleId]);
        $this->assertTrue(Hash::check('long-test-password', DB::table('users')->where('id', $id)->value('password')));
        $this->assertDatabaseHas('users', ['id' => $id, 'sales_identifier' => 'G']);
        $this->actingAs(User::findOrFail($id))->get(route('access.index'))->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->seller)->post(route('access.role'), ['name' => 'Escalamiento', 'permissions' => ['access.manage']])->assertForbidden();
        $this->expectException(BusinessRuleViolation::class);
        $manager->saveUser($this->admin->id, $this->admin->id, $this->admin->name, $this->admin->email, null, null, false, []);
    }

    public function test_seller_identifier_is_enforced_and_retries_keep_the_global_order_number(): void
    {
        $token = $this->token();
        $service = app(RegisterOrder::class);
        $lines = [['variant_id' => $this->variantId, 'quantity' => 5]];
        $id = $service->handle($this->seller->id, $token, $this->customerId, 'S', $lines);
        $this->assertSame($id, $service->handle($this->seller->id, $token, $this->customerId, 'S', $lines));
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'origin' => 'S', 'number' => 'NP-000001']);
        $this->expectException(BusinessRuleViolation::class);
        $service->handle($this->seller->id, $this->token(), $this->customerId, 'G', $lines);
    }

    public function test_cuit_is_required_unique_and_credit_history_can_be_updated(): void
    {
        $customers = app(ManageCustomers::class);
        $customers->updateCredit($this->admin->id, $this->customerId, 'Nueva verificación manual');
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'credit_history' => 'Nueva verificación manual']);
        $this->assertDatabaseCount('customer_credit_entries', 2);
        $this->assertDatabaseHas('customer_credit_entries', ['customer_id' => $this->customerId, 'actor_id' => $this->admin->id, 'note' => 'Saldo de cuenta corriente: pendiente de cobro']);
        $this->expectException(BusinessRuleViolation::class);
        $customers->register($this->admin->id, 'Otro cliente', '20123456789', '');
    }

    public function test_authenticated_pages_and_balance_snapshot_render_with_role_isolation(): void
    {
        $lineId = $this->order();
        $this->receive();
        $this->actingAs($this->admin)->get(route('access.index'))->assertOk()->assertSee('Crear usuario');
        $this->get(route('operations.dispatch.index'))->assertOk()->assertSee('data-refresh-url', false)->assertSee('Confirmar despacho o retiro');
        $this->get(route('operations.snapshot'))->assertOk()->assertSee('Mercadería pendiente')->assertDontSee('<form', false);
        $this->actingAs($this->seller)->get(route('operations.orders'))->assertOk()->assertSee('Registrar nota de pedido')->assertDontSee('Validación administrativa');
        $this->actingAs($this->seller)->post(route('operations.receive'), ['actor_id' => $this->warehouse->id])->assertForbidden();
        DB::table('users')->where('id', $this->warehouse->id)->update(['is_active' => false]);
        $this->actingAs($this->warehouse)->get(route('operations.index'))->assertForbidden();
    }

    public function test_zero_quantity_and_wrong_variant_are_rejected_without_side_effects(): void
    {
        $batchId = $this->receive();
        $lineId = $this->order();
        $this->authorize($lineId);
        $otherVariant = app(PrepareInventory::class)->variant($this->admin->id, 'OTRO', 'Zapato', 'Marrón', '41');
        $otherBatch = app(MoveStock::class)->opening($this->admin->id, $this->token(), $otherVariant, 'LOTE-COMPARTIDO', '2026-01-01', 5);
        $otherBatchId = DB::table('inventory_movements')->where('id', $otherBatch)->value('batch_id');

        foreach ([[0, $batchId], [1, $otherBatchId]] as [$quantity, $batch]) {
            try {
                app(MoveStock::class)->dispatch($this->warehouse->id, $this->token(), $lineId, $batch, $quantity);
                $this->fail('La operación inválida no debe continuar.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseHas('business_order_lines', ['id' => $lineId, 'dispatched' => 0]);
            }
        }

        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_context_autoload_factory_and_migration_discovery_preserve_the_installed_identity(): void
    {
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertInstanceOf(User::class, User::factory()->make());
        $this->assertInstanceOf(User::class, UserFactory::new()->make());
        $migrationName = '2026_11_04_200000_create_operations_foundation';
        $files = app('migrator')->getMigrationFiles([
            ...app('migrator')->paths(), database_path('migrations'),
        ]);

        $this->assertSame(
            realpath(base_path('src/Shared/Infrastructure/Database/Migrations/'.$migrationName.'.php')),
            realpath($files[$migrationName]),
        );
        $this->assertFileDoesNotExist(database_path('migrations/'.$migrationName.'.php'));
        $this->artisan('migrate', ['--pretend' => true])->expectsOutputToContain('Nothing to migrate')->assertSuccessful();
        $this->assertDatabaseCount('inventory_audit_head', 1);
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'cuit' => '20123456789']);
    }
}
