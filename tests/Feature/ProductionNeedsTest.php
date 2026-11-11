<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Application\ProductionPlanningQueries;
use Simon\InventarioTrazabilidad\Application\QueryProductionNeeds;
use Simon\Shared\Application\ReadSourceUnavailable;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Application\PendingDemand;
use Simon\VentasPedidos\Application\PendingOrderQueries;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class ProductionNeedsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $production;

    private int $variant;

    private int $customer;

    private int $address;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.audit_key' => str_repeat('production-needs-test-key-', 3)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->production = $this->user(['production.manage']);
        $this->variant = app(PrepareInventory::class)->variant($this->admin->id, 'NEC-01', 'Botín', 'Negro', '40');
        $this->customer = app(ManageCustomers::class)->register($this->admin->id, 'CLIENTE-PRIVADO', '20123456789', 'CREDITO-PRIVADO');
        $this->address = app(ManageAddresses::class)->save($this->admin->id, $this->customer, null, 'Depósito', 'Calle 123', 'Posadas', 'Misiones');
    }

    /** @param list<string> $permissions */
    private function user(array $permissions): User
    {
        $user = User::factory()->create();
        $role = DB::table('access_roles')->insertGetId(['name' => 'Necesidades '.Str::uuid()]);

        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
        }

        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);

        return $user;
    }

    private function order(int $quantity, bool $draft = false): int
    {
        return app(RegisterOrder::class)->handle($this->admin->id, (string) Str::uuid(), $this->customer, 'F', [['variant_id' => $this->variant, 'quantity' => $quantity]], $this->address, $draft);
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        return app(QueryProductionNeeds::class)->handle($this->production->id)['items'][0];
    }

    public function test_acceptance_formula_is_read_only_and_visible_in_the_native_production_panel(): void
    {
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'INICIAL', '2026-01-01', 10);
        app(PrepareInventory::class)->production($this->admin->id, (string) Str::uuid(), $this->variant, 'TANDA', '2026-01-01', 4);
        $this->order(20);
        $head = (array) DB::table('inventory_audit_head')->first();
        $before = [DB::table('inventory_movements')->count(), DB::table('business_order_changes')->count()];
        $row = $this->row();
        $this->assertSame([10, 4, 20, -6, 6], [$row['available'], $row['in_process'], $row['pending'], $row['balance'], $row['injection_needed']]);

        $this->actingAs($this->production)->get(route('operations.production.index'))->assertOk()
            ->assertSee('fi-main-sidebar', false)->assertSee('Necesidades de producción')->assertSee('NEC-01')
            ->assertSee('data-projected-balance="-6"', false)->assertSee('data-injection-needed="6"', false)
            ->assertDontSee('CLIENTE-PRIVADO')->assertDontSee('CREDITO-PRIVADO');
        $response = $this->get(route('operations.snapshot', ['section' => 'production']))->assertOk()
            ->assertSee('data-projected-balance="-6"', false)->assertDontSee('<form', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($head, (array) DB::table('inventory_audit_head')->first());
        $this->assertSame($before, [DB::table('inventory_movements')->count(), DB::table('business_order_changes')->count()]);
        $this->assertDatabaseHas('inventory_batches', ['variant_id' => $this->variant, 'quantity' => 10]);
        $this->assertDatabaseHas('production_batches', ['variant_id' => $this->variant, 'in_process' => 4]);
        $this->assertDatabaseHas('business_customers', ['id' => $this->customer, 'credit_history' => 'CREDITO-PRIVADO']);
    }

    public function test_changes_and_idempotent_receipts_dispatches_and_cancellations_do_not_duplicate_effects(): void
    {
        $stockMovement = app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'INICIAL', '2026-01-01', 10);
        $batch = DB::table('inventory_movements')->where('id', $stockMovement)->value('batch_id');
        $production = app(PrepareInventory::class)->production($this->admin->id, (string) Str::uuid(), $this->variant, 'TANDA', '2026-01-01', 4);
        $order = $this->order(20);
        $this->assertSame(-6, $this->row()['balance']);
        $token = (string) Str::uuid();
        $movement = app(MoveStock::class)->receive($this->admin->id, $token, $production, 2);
        $this->assertSame($movement, app(MoveStock::class)->receive($this->admin->id, $token, $production, 2));
        $row = $this->row();
        $this->assertSame([12, 2, -6], [$row['available'], $row['in_process'], $row['balance']]);

        app(ManageCustomers::class)->authorize($this->admin->id, $order, true, true, 'Autorizado');
        $line = DB::table('business_order_lines')->where('order_id', $order)->value('id');
        $token = (string) Str::uuid();
        app(MoveStock::class)->dispatch($this->admin->id, $token, $line, $batch, 3);
        app(MoveStock::class)->dispatch($this->admin->id, $token, $line, $batch, 3);
        $row = $this->row();
        $this->assertSame([9, 2, 17, -6], [$row['available'], $row['in_process'], $row['pending'], $row['balance']]);

        app(PrepareInventory::class)->production($this->admin->id, (string) Str::uuid(), $this->variant, 'OTRA', '2026-01-01', 6);
        $this->assertSame(0, $this->row()['balance']);
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'OTRO-STOCK', '2026-01-01', 1);
        $this->assertSame(1, $this->row()['balance']);
        $extra = $this->order(5);
        $this->assertSame(-4, $this->row()['balance']);
        app(ManageOrders::class)->transition($this->admin->id, (string) Str::uuid(), $extra, 0, 'cancel', 'Anulado');
        $this->assertSame(1, $this->row()['balance']);
        $draft = $this->order(100, true);
        $this->assertSame(1, $this->row()['balance']);
        app(ManageOrders::class)->transition($this->admin->id, (string) Str::uuid(), $draft, 0, 'confirm');
        $this->assertSame(-99, $this->row()['balance']);
        $this->actingAs($this->production)->get(route('operations.snapshot', ['section' => 'production']))->assertOk()->assertSee('data-projected-balance="-99"', false);
    }

    public function test_zero_sources_are_valid_and_another_variant_cannot_cover_a_deficit(): void
    {
        $other = app(PrepareInventory::class)->variant($this->admin->id, 'NEC-02', 'Botín', 'Negro', '41');
        $this->assertSame([0, 0, 0, 0, 0], array_values(array_intersect_key($this->row(), array_flip(['available', 'in_process', 'pending', 'balance', 'injection_needed']))));
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $other, 'EXCEDENTE', '2026-01-01', 100);
        $this->order(20);
        $items = app(QueryProductionNeeds::class)->handle($this->production->id)['items'];
        $this->assertSame([-20, 100], array_column($items, 'balance'));
        $this->assertSame([20, 0], array_column($items, 'injection_needed'));
    }

    public function test_complete_sources_are_aggregated_beyond_old_limits_and_catalog_pagination_preserves_both_pages(): void
    {
        for ($index = 1; $index <= 501; $index++) {
            DB::table('catalog_variants')->insert(['code' => 'NEC-MAS-'.$index, 'model' => 'Modelo '.$index, 'color' => 'Negro', 'size' => (string) $index]);
            DB::table('inventory_batches')->insert(['variant_id' => $this->variant, 'lot' => 'STOCK-'.$index, 'manufactured_on' => '2026-01-01', 'quantity' => 1]);
            DB::table('production_batches')->insert(['registration_id' => (string) Str::uuid(), 'variant_id' => $this->variant, 'lot' => 'TANDA-'.$index, 'manufactured_on' => '2026-01-01', 'in_process' => 1]);
        }

        for ($index = 1; $index <= 201; $index++) {
            $order = DB::table('business_orders')->insertGetId(['registration_id' => (string) Str::uuid(), 'request_digest' => hash('sha256', 'fixture-'.$index), 'number' => 'NEC-FIXTURE-'.$index,
                'customer_id' => $this->customer, 'seller_id' => $this->admin->id, 'origin' => 'F', 'status' => 'Confirmado', 'created_at' => now()]);
            DB::table('business_order_lines')->insert(['order_id' => $order, 'variant_id' => $this->variant, 'quantity' => 10, 'dispatched' => 0]);
        }

        $report = app(QueryProductionNeeds::class)->handle($this->production->id);
        $this->assertSame([502, 21, 25], [$report['total'], $report['pages'], count($report['items'])]);
        $this->assertSame([501, 501, 2010, -1008], array_map(fn (string $field): int => $report['items'][0][$field], ['available', 'in_process', 'pending', 'balance']));
        $last = app(QueryProductionNeeds::class)->handle($this->production->id, 999);
        $this->assertSame(21, $last['page']);
        $this->assertSame('NEC-MAS-501', $last['items'][1]['code']);
        $this->assertSame(0, $last['items'][1]['pending']);
        $this->actingAs($this->production)->get(route('operations.production.index', ['needs_page' => 21, 'article_page' => 2]))->assertOk()
            ->assertSee('NEC-MAS-501')->assertSee('needs_page=21', false)->assertSee('Necesidades: página 21 de 21');
    }

    public function test_only_production_and_inventory_readers_can_use_the_projection_and_demand_contract(): void
    {
        $reader = $this->user(['inventory.view']);
        $this->assertSame(0, app(QueryProductionNeeds::class)->handle($reader->id)['items'][0]['balance']);

        foreach (['inventory.receive', 'orders.create', 'orders.manage', 'catalog.manage', 'inventory.dispatch', 'dispatch.authorize', 'audit.view'] as $permission) {
            $user = $this->user([$permission]);

            foreach ([fn (): array => app(QueryProductionNeeds::class)->handle($user->id), fn (): array => app(PendingDemand::class)->quantities($user->id, [$this->variant])] as $query) {
                try {
                    $query();
                    $this->fail('Un permiso ajeno no habilita la proyección.');
                } catch (PermissionDenied) {
                    $this->assertTrue(true);
                }
            }

            $response = $this->actingAs($user)->get(route('operations.snapshot', ['section' => 'production']));
            $permission === 'inventory.receive' ? $response->assertOk()->assertDontSee('Necesidades de producción') : $response->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_revoked_permissions_remove_the_projection_on_the_next_refresh(): void
    {
        $this->actingAs($this->production)->get(route('operations.production.index'))->assertOk()->assertSee('Necesidades de producción');
        DB::table('access_user_roles')->where('user_id', $this->production->id)->delete();
        $this->get(route('operations.snapshot', ['section' => 'production']))->assertForbidden();
        $this->expectException(PermissionDenied::class);
        app(QueryProductionNeeds::class)->handle($this->production->id);
    }

    public function test_missing_demand_is_an_explicit_unavailable_source_and_not_zero(): void
    {
        $demand = Mockery::mock(PendingDemand::class);
        $demand->shouldReceive('quantities')->andReturn([]);
        $this->app->instance(PendingDemand::class, $demand);
        $this->actingAs($this->production)->get(route('operations.production.index'))->assertOk()
            ->assertSee('data-production-unavailable', false)->assertSee('No disponible.')
            ->assertDontSee('data-projected-balance', false);
        $this->get(route('operations.snapshot', ['section' => 'production']))->assertOk()
            ->assertSee('data-production-unavailable', false)->assertDontSee('data-projected-balance', false);
    }

    public function test_an_unavailable_inventory_source_is_reported_without_a_fabricated_projection(): void
    {
        $planning = Mockery::mock(ProductionPlanningQueries::class);
        $planning->shouldReceive('snapshot')->andThrow(new ReadSourceUnavailable('Fuente temporalmente no disponible'));
        $this->app->instance(ProductionPlanningQueries::class, $planning);
        $this->actingAs($this->production)->get(route('operations.snapshot', ['section' => 'production']))->assertOk()
            ->assertSee('data-production-unavailable', false)->assertDontSee('data-projected-balance', false);
    }

    public function test_sql_adapters_translate_failed_reads_to_an_unavailable_source(): void
    {
        $connection = DB::connection();
        $prefix = $connection->getTablePrefix();
        $connection->setTablePrefix('rf010_missing_');

        try {
            foreach ([fn (): array => app(ProductionPlanningQueries::class)->snapshot(fn (): array => []), fn (): array => app(PendingOrderQueries::class)->quantities([$this->variant])] as $query) {
                try {
                    $query();
                    $this->fail('Una lectura fallida no equivale a cero pares.');
                } catch (ReadSourceUnavailable $exception) {
                    $this->assertNotNull($exception->getPrevious());
                }
            }
        } finally {
            $connection->setTablePrefix($prefix);
        }
    }

    public function test_empty_catalog_and_invalid_page_are_distinct_from_unavailable_sources(): void
    {
        DB::table('catalog_variants')->delete();
        $this->actingAs($this->production)->get(route('operations.production.index'))->assertOk()
            ->assertSee('Todavía no hay artículos registrados para calcular necesidades.')->assertDontSee('data-production-unavailable', false);
        $this->get(route('operations.snapshot', ['section' => 'production', 'needs_page' => 0]))->assertInvalid('needs_page');
        $this->expectException(BusinessRuleViolation::class);
        app(QueryProductionNeeds::class)->handle($this->production->id, 0);
    }
}
