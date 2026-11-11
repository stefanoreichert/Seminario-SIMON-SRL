<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\CurrentAccountRecords;
use Simon\ClientesAdministracion\Application\ManageCurrentAccounts;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\ClientesAdministracion\Infrastructure\SqlCurrentAccounts;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class CurrentAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.audit_key' => str_repeat('account-physical-audit-key-', 3)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente financiero', '20123456789', 'Historia manual conservada');
    }

    private function postEntry(string $kind, string $amount, ?string $token = null, ?int $customer = null, string $note = ''): int
    {
        return app(ManageCurrentAccounts::class)->post($this->admin->id, $token ?? (string) Str::uuid(), $customer ?? $this->customer, $kind, $amount, 'Fuente de prueba '.$kind, $note);
    }

    private function balance(?int $customer = null): ?int
    {
        return app(ManageCurrentAccounts::class)->overview($this->admin->id, $customer ?? $this->customer)['account']['balance_minor'] ?? null;
    }

    public function test_a_missing_opening_is_unknown_and_an_explicit_zero_is_a_real_balance(): void
    {
        $this->assertNull($this->balance());
        $this->actingAs($this->admin)->get(route('current-accounts.index', ['customer_id' => $this->customer]))->assertOk()->assertSee('data-account-uninitialized', false)->assertDontSee('data-account-balance', false);
        $this->postEntry('opening', '0');
        $this->assertSame(0, $this->balance());
        $this->get(route('current-accounts.index', ['customer_id' => $this->customer]))->assertOk()->assertSee('data-account-balance="0"', false)->assertSee('0,00 ARS')->assertDontSee('data-account-uninitialized', false);
    }

    public function test_charges_payments_adjustments_and_reconciliation_affect_only_the_correct_account(): void
    {
        $physicalHead = (array) DB::table('inventory_audit_head')->first();
        $this->postEntry('opening', '1000');
        $this->postEntry('charge', '250,10');
        $this->postEntry('payment', '200');
        $this->postEntry('adjustment', '-50.10', note: 'Corrección contrastada con la fuente');
        $this->postEntry('reconciliation', '1000');
        $other = app(ManageCustomers::class)->register($this->admin->id, 'Otra cuenta', '20223456789', 'Otra historia');
        $this->postEntry('opening', '50', customer: $other);
        $this->postEntry('payment', '100', customer: $other);
        $this->assertSame(100000, $this->balance());
        $this->assertSame(-5000, $this->balance($other));
        $this->assertDatabaseHas('customer_current_account_entries', ['kind' => 'reconciliation', 'amount_minor' => 0, 'balance_after_minor' => 100000, 'actor_id' => $this->admin->id]);
        $this->assertSame($physicalHead, (array) DB::table('inventory_audit_head')->first());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('business_customers', ['id' => $this->customer, 'credit_history' => 'Historia manual conservada']);
    }

    public function test_a_complete_delivery_can_leave_the_financial_debt_unchanged(): void
    {
        $this->postEntry('opening', '1234,50');
        $variant = app(PrepareInventory::class)->variant($this->admin->id, 'FIN-ART', 'Botín', 'Negro', '40');
        $order = app(RegisterOrder::class)->handle($this->admin->id, (string) Str::uuid(), $this->customer, 'F', [['variant_id' => $variant, 'quantity' => 5]]);
        $line = (int) DB::table('business_order_lines')->where('order_id', $order)->value('id');
        app(ManageCustomers::class)->authorize($this->admin->id, $order, true, true, 'A cuenta corriente');
        $movement = app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $variant, 'LOTE-FIN', '2026-01-01', 5);
        $batch = (int) DB::table('inventory_movements')->where('id', $movement)->value('batch_id');
        app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), $line, $batch, 5, true);
        $this->assertDatabaseHas('business_orders', ['id' => $order, 'status' => 'Entregado']);
        $this->assertSame(123450, $this->balance());
        $this->assertDatabaseCount('customer_current_account_entries', 1);
    }

    public function test_retries_preserve_the_entry_and_conflicting_content_is_rejected(): void
    {
        $token = (string) Str::uuid();
        $id = $this->postEntry('opening', '1.0', $token);
        $this->assertSame($id, $this->postEntry('opening', '1,00', $token));
        $this->assertSame(100, $this->balance());
        $this->assertDatabaseCount('customer_current_account_entries', 1);
        $this->expectException(BusinessRuleViolation::class);
        $this->postEntry('opening', '2', $token);
    }

    public function test_rejected_inputs_do_not_create_an_account_or_corrupt_its_balance(): void
    {
        foreach ([['charge', '1', ''], ['opening', '1,001', ''], ['adjustment', '1', '']] as [$kind, $amount, $note]) {
            try {
                $this->postEntry($kind, $amount, note: $note);
                $this->fail('No debe registrarse un movimiento inválido.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseCount('customer_current_accounts', 0);
            }
        }

        $this->postEntry('opening', '10');

        foreach ([['opening', '1'], ['payment', '-1'], ['charge', '0'], ['reconciliation', '9']] as [$kind, $amount]) {
            try {
                $this->postEntry($kind, $amount);
                $this->fail('La apertura duplicada, signo inválido o diferencia no puede alterar el saldo.');
            } catch (BusinessRuleViolation) {
                $this->assertSame(1000, $this->balance());
                $this->assertDatabaseCount('customer_current_account_entries', 1);
            }
        }
    }

    public function test_only_explicit_financial_permissions_allow_reading_or_writing_and_currency_is_not_forged(): void
    {
        $this->postEntry('opening', '10');
        $reader = User::factory()->create();
        $role = DB::table('access_roles')->insertGetId(['name' => 'Solo cuenta corriente']);
        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => 'finance.view']);
        DB::table('access_user_roles')->insert(['user_id' => $reader->id, 'role_id' => $role]);
        $this->actingAs($reader)->get(route('current-accounts.index', ['customer_id' => $this->customer]))->assertOk()->assertSee('fi-main-sidebar', false)->assertSee('10,00 ARS')->assertDontSee('Registrar movimiento financiero');
        $this->post(route('current-accounts.store'), ['actor_id' => $this->admin->id])->assertForbidden();
        DB::table('access_role_permissions')->where('role_id', $role)->update(['permission' => 'clients.manage']);
        $this->get(route('current-accounts.index', ['customer_id' => $this->customer]))->assertForbidden();
        $this->expectException(BusinessRuleViolation::class);
        app(ManageCurrentAccounts::class)->post($this->admin->id, (string) Str::uuid(), $this->customer, 'charge', '1', 'Fuente', currency: 'USD');
    }

    public function test_history_is_immutable_and_its_current_balance_is_independent_of_the_requested_page(): void
    {
        $this->postEntry('opening', '0');

        for ($index = 0; $index < 30; $index++) {
            $this->postEntry('charge', '0,01');
        }

        $report = app(ManageCurrentAccounts::class)->overview($this->admin->id, $this->customer, entryPage: 2);
        $this->assertSame(30, $report['account']['balance_minor']);
        $this->assertSame(31, $report['entries']['total']);
        $this->assertCount(6, $report['entries']['items']);

        foreach (['customer_current_accounts', 'customer_current_account_entries'] as $table) {
            foreach (['update', 'delete'] as $action) {
                try {
                    $action === 'delete' ? DB::table($table)->delete() : DB::table($table)->update($table === 'customer_current_accounts' ? ['currency' => 'USD'] : ['amount_minor' => 999]);
                    $this->fail('El historial financiero debe conservarse.');
                } catch (QueryException $exception) {
                    $this->assertStringContainsString('inmutable', $exception->getMessage());
                }
            }
        }
    }

    public function test_an_entry_failure_rolls_back_the_account_opening(): void
    {
        $store = new SqlCurrentAccounts;
        $records = Mockery::mock(CurrentAccountRecords::class);
        $records->shouldReceive('run')->andReturnUsing($store->run(...));
        $records->shouldReceive('locked')->andReturnUsing($store->locked(...));
        $records->shouldReceive('replay')->andReturnUsing($store->replay(...));
        $records->shouldReceive('create')->andReturnUsing($store->create(...));
        $records->shouldReceive('append')->andThrow(new BusinessRuleViolation('Fallo de persistencia'));
        $this->app->instance(CurrentAccountRecords::class, $records);

        try {
            $this->postEntry('opening', '10');
            $this->fail('No puede quedar una cuenta abierta sin su movimiento inicial.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseCount('customer_current_accounts', 0);
            $this->assertDatabaseCount('customer_current_account_entries', 0);
        }
    }
}
