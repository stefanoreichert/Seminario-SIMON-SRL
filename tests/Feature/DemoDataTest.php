<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\QueryProductionNeeds;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\Shared\Application\AccessPolicy;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        config(['inventory.audit_key' => str_repeat('demo-test-audit-key-', 3)]);
        $this->administrator = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->administrator->email])->assertSuccessful();
    }

    public function test_demo_is_connected_repeatable_and_preserves_existing_data_and_permissions(): void
    {
        $existing = app(ManageCustomers::class)->register($this->administrator->id, 'Cliente existente', '20123456789', 'Conservar');
        $permissions = DB::table('access_role_permissions')->where('role_id', 1)->orderBy('permission')->get()->toJson();
        $this->artisan('demo:create', ['--actor' => $this->administrator->email])->assertSuccessful();
        $this->assertDatabaseHas('business_customers', ['id' => $existing, 'name' => 'Cliente existente', 'credit_history' => 'Conservar']);
        $this->assertDatabaseHas('business_orders', ['status' => 'Parcial']);
        $this->assertDatabaseHas('business_order_lines', ['quantity' => 20, 'dispatched' => 1]);
        $this->assertSame(12, (int) DB::table('inventory_batches')->sum('quantity'));
        $this->assertSame(4, (int) DB::table('production_batches')->sum('in_process'));
        $this->assertSame(1, DB::table('inventory_pair_units')->where('state', 'picked')->count());
        $this->assertSame(1, DB::table('inventory_pair_units')->where('state', 'dispatched')->count());
        $this->assertDatabaseCount('inventory_pair_units', 8);
        $this->assertDatabaseCount('inventory_supply_lots', 1);
        $this->assertDatabaseCount('customer_current_account_entries', 5);
        $this->assertSame(11450000, (int) DB::table('customer_current_account_entries')->orderByDesc('id')->value('balance_after_minor'));
        $actor = (int) DB::table('users')->where('email', 'demo.operacion@example.test')->value('id');
        $this->assertFalse(app(AccessPolicy::class)->allows($actor, 'access.manage'));
        $this->assertTrue(app(AccessPolicy::class)->allows($actor, 'finance.view'));
        $this->assertSame(3, app(SqlInventory::class)->verify()['sequence']);
        $projection = app(QueryProductionNeeds::class)->handle($actor);
        $this->assertSame(3, $projection['items'][0]['injection_needed']);
        $this->actingAs(User::findOrFail($actor));
        foreach ([
            ['customers.index', [], 'DEMO · Calzados del Sur'],
            ['operations.orders', [], 'NP-000001'],
            ['operations.production.index', [], 'DEMO-FABRICA-001'],
            ['operations.stock', [], 'DEMO-INICIAL-001'],
            ['picking.index', ['line_id' => DB::table('business_order_lines')->value('id')], 'Pares seleccionados'],
            ['traceability.index', ['lot' => 'DEMO-FABRICA-001'], 'DEMO · Calzados del Sur'],
            ['supplies.index', [], 'DEMO-INSUMO-001'],
            ['current-accounts.index', ['customer_id' => DB::table('business_customers')->where('cuit', '20999000123')->value('id')], '114500,00 ARS'],
        ] as [$route, $parameters, $text]) {
            $this->get(route($route, $parameters))->assertOk()->assertSee($text);
        }
        $this->flushSession();
        $this->actingAs($this->administrator)->get(route('access.index'))->assertOk()->assertSee('DEMO · Operador de presentación');
        $this->get(route('access.roles'))->assertOk()->assertSee('DEMO · Operación');
        $before = $this->snapshot();
        $this->artisan('demo:create', ['--actor' => $this->administrator->email])->assertSuccessful();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($permissions, DB::table('access_role_permissions')->where('role_id', 1)->orderBy('permission')->get()->toJson());
    }

    public function test_missing_financial_schema_does_not_prevent_the_other_demo_examples(): void
    {
        Schema::drop('customer_current_account_entries');
        Schema::drop('customer_current_accounts');
        $this->artisan('demo:create', ['--actor' => $this->administrator->email])
            ->expectsOutputToContain('La muestra operativa está creada')->assertSuccessful();
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertFalse(Schema::hasTable('customer_current_accounts'));
    }

    public function test_a_collision_with_existing_customer_data_rolls_back_the_demo_additions(): void
    {
        app(ManageCustomers::class)->register($this->administrator->id, 'Conservar cliente ajeno', '20999000123', 'Original');
        $this->artisan('demo:create', ['--actor' => $this->administrator->email])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'demo.operacion@example.test']);
        $this->assertDatabaseMissing('access_roles', ['name' => 'DEMO · Operación']);
        $this->assertDatabaseHas('business_customers', ['name' => 'Conservar cliente ajeno', 'credit_history' => 'Original']);
        $this->assertDatabaseCount('business_orders', 0);
    }

    public function test_an_unprivileged_actor_cannot_create_demo_users_or_business_data(): void
    {
        $user = User::factory()->create();
        $this->artisan('demo:create', ['--actor' => $user->email])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'demo.operacion@example.test']);
        $this->assertDatabaseCount('business_customers', 0);
    }

    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        $tables = ['users', 'access_roles', 'access_user_roles', 'business_customers', 'customer_delivery_addresses', 'customer_credit_entries', 'catalog_variants', 'business_orders', 'business_order_lines', 'production_batches', 'inventory_batches', 'inventory_movements', 'inventory_audits', 'inventory_pair_units', 'inventory_supply_lots', 'customer_current_account_entries'];

        return collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->toJson()])->all();
    }
}
