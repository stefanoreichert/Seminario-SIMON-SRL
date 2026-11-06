<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Application\PendingDemand;
use Simon\VentasPedidos\Application\QueryPendingOrders;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class PendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $production;

    private int $customer;

    private int $variant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.audit_key' => str_repeat('pending-orders-test-key-', 3)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->seller = $this->user(['orders.create'], 'S');
        $this->production = $this->user(['production.manage']);
        $this->customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente visible', '20123456789', 'CREDITO-PRIVADO-NO-MOSTRAR');
        $this->variant = app(PrepareInventory::class)->variant($this->admin->id, 'PP-01', 'Botín', 'Negro', '40');
    }

    /** @param list<string> $permissions */
    private function user(array $permissions, ?string $identifier = null): User
    {
        $user = User::factory()->create(['sales_identifier' => $identifier]);
        $role = DB::table('access_roles')->insertGetId(['name' => 'PP '.Str::uuid()]);

        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
        }

        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);

        return $user;
    }

    private function order(int $quantity, bool $draft = false, ?User $actor = null, ?int $customer = null, ?int $variant = null): int
    {
        $actor ??= $this->seller;

        return app(RegisterOrder::class)->handle($actor->id, (string) Str::uuid(), $customer ?? $this->customer, $actor->sales_identifier ?? 'F', [['variant_id' => $variant ?? $this->variant, 'quantity' => $quantity]], null, $draft);
    }

    public function test_summaries_follow_confirmation_dispatch_retry_and_cancellation_without_double_counting(): void
    {
        $id = $this->order(10, true);
        $queries = app(QueryPendingOrders::class);
        $this->assertSame(['pairs' => 0, 'orders' => 0, 'articles' => 0], $queries->handle($this->admin->id)['totals']);
        app(ManageOrders::class)->transition($this->seller->id, (string) Str::uuid(), $id, 0, 'confirm');
        $this->assertSame(10, $queries->handle($this->admin->id)['totals']['pairs']);
        $line = DB::table('business_order_lines')->where('order_id', $id)->value('id');
        app(ManageCustomers::class)->authorize($this->admin->id, $id, true, true, 'Autorizado para prueba');
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'PP-LOTE', '2026-01-01', 20);
        $batch = DB::table('inventory_batches')->value('id');
        $token = (string) Str::uuid();
        app(MoveStock::class)->dispatch($this->admin->id, $token, $line, $batch, 4, true);
        app(MoveStock::class)->dispatch($this->admin->id, $token, $line, $batch, 4, true);
        $report = $queries->handle($this->admin->id);
        $this->assertSame(['pairs' => 6, 'orders' => 1, 'articles' => 1], $report['totals']);
        $this->assertSame(6, $report['customers']['items'][0]['pairs']);
        $this->assertSame(6, $report['articles']['items'][0]['pairs']);
        $this->assertSame('Cliente visible', $report['customers']['items'][0]['customer']['name']);
        $this->assertSame('PP-01', $report['articles']['items'][0]['article']['code']);
        $this->actingAs($this->admin)->get(route('pending.snapshot'))->assertOk()->assertSee('data-pending-pairs="6"', false)->assertDontSee('CREDITO-PRIVADO-NO-MOSTRAR');
        $token = (string) Str::uuid();
        app(ManageOrders::class)->transition($this->seller->id, $token, $id, 2, 'cancel', 'Cancelar saldo restante');
        app(ManageOrders::class)->transition($this->seller->id, $token, $id, 2, 'cancel', 'Cancelar saldo restante');
        $this->assertSame(0, $queries->handle($this->admin->id)['totals']['pairs']);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batch, 'quantity' => 16]);
        $this->assertDatabaseHas('business_order_lines', ['id' => $line, 'quantity' => 10, 'dispatched' => 4]);
    }

    public function test_grouping_combines_all_orders_per_customer_and_variant_and_excludes_terminal_states(): void
    {
        $this->order(10);
        $this->order(7);
        $variant = app(PrepareInventory::class)->variant($this->admin->id, 'PP-02', 'Zapato', 'Marrón', '42');
        $this->order(5, variant: $variant);
        $other = app(ManageCustomers::class)->register($this->admin->id, 'Otro cliente', '20223456789', 'Privado');
        $this->order(3, customer: $other);
        $draft = $this->order(100, true);
        $cancelled = $this->order(99);
        app(ManageOrders::class)->transition($this->admin->id, (string) Str::uuid(), $cancelled, 0, 'cancel', 'Prueba');
        $delivered = $this->order(98);
        DB::table('business_order_lines')->where('order_id', $delivered)->update(['dispatched' => 98]);
        DB::table('business_orders')->where('id', $delivered)->update(['status' => 'Entregado']);
        $report = app(QueryPendingOrders::class)->handle($this->admin->id);
        $this->assertSame(['pairs' => 25, 'orders' => 4, 'articles' => 2], $report['totals']);
        $this->assertCount(3, $report['customers']['items']);
        $this->assertSame(17, $report['customers']['items'][0]['pairs']);
        $this->assertSame(2, $report['customers']['items'][0]['orders']);
        $this->assertSame([20, 5], array_column($report['articles']['items'], 'pairs'));
        $this->assertDatabaseHas('business_orders', ['id' => $draft, 'status' => 'Borrador']);
    }

    public function test_seller_scope_is_applied_before_totals_grouping_pagination_and_origin_filter(): void
    {
        $this->order(10);
        $other = $this->user(['orders.create'], 'G');
        $this->order(7, actor: $other);
        $this->order(5, actor: $this->admin);
        $queries = app(QueryPendingOrders::class);
        $this->assertSame(22, $queries->handle($this->admin->id)['totals']['pairs']);
        $this->assertSame(10, $queries->handle($this->seller->id)['totals']['pairs']);
        $this->assertSame(0, $queries->handle($this->seller->id, 'G')['totals']['pairs']);
        $this->assertSame(7, $queries->handle($this->admin->id, 'G')['totals']['pairs']);
        $this->actingAs($this->seller)->get(route('pending.snapshot', ['seller_id' => $other->id, 'origin' => 'G']))
            ->assertOk()->assertSee('data-pending-pairs="0"', false)->assertDontSee('<form', false);
    }

    public function test_production_reads_global_article_demand_without_customer_data_or_commercial_order_access(): void
    {
        $this->order(10);
        $this->order(7, actor: $this->admin);
        $report = app(PendingDemand::class)->forProduction($this->production->id);
        $this->assertSame(['pairs' => 17, 'orders' => 2, 'articles' => 1], $report['totals']);
        $this->assertArrayNotHasKey('customers', $report);
        $this->actingAs($this->production)->get(route('operations.production.index'))->assertOk()
            ->assertSee('Demanda pendiente por artículo')->assertSee('PP-01')->assertSee('data-pending-pairs="17"', false)
            ->assertDontSee('Cliente visible')->assertDontSee('CREDITO-PRIVADO-NO-MOSTRAR')->assertDontSee('Pendientes por cliente');
        $this->get(route('operations.snapshot', ['section' => 'production']))->assertOk()->assertSee('PP-01')->assertDontSee('Cliente visible')->assertDontSee('<form', false);
        $this->get(route('pending.index'))->assertForbidden();
        $this->get(route('pending.snapshot'))->assertForbidden();
    }

    public function test_customer_summaries_are_read_only_private_and_available_only_to_the_existing_readers(): void
    {
        $this->order(10);
        $head = (array) DB::table('inventory_audit_head')->first();

        foreach (['orders.manage', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize'] as $permission) {
            $reader = $this->user([$permission]);
            $response = $this->actingAs($reader)->get(route('pending.index'))->assertOk()->assertSee('fi-main-sidebar', false)
                ->assertSee('Cliente visible')->assertSee('PP-01')->assertDontSee('CREDITO-PRIVADO-NO-MOSTRAR')
                ->assertHeader('Cache-Control');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        }

        $receiver = $this->user(['inventory.receive']);
        $this->actingAs($receiver)->get(route('operations.production.index'))->assertOk()->assertDontSee('Demanda pendiente por artículo');
        $this->get(route('pending.snapshot'))->assertForbidden();
        $this->assertSame($head, (array) DB::table('inventory_audit_head')->first());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('business_order_changes', 0);
        $this->expectException(PermissionDenied::class);
        app(PendingDemand::class)->forProduction($this->seller->id);
    }

    public function test_old_pending_orders_are_counted_beyond_the_recent_200_note_limit(): void
    {
        $this->order(3);

        for ($index = 0; $index < 205; $index++) {
            $id = DB::table('business_orders')->insertGetId(['registration_id' => (string) Str::uuid(), 'request_digest' => hash('sha256', (string) $index), 'number' => 'PP-FIXTURE-'.$index,
                'customer_id' => $this->customer, 'seller_id' => $this->seller->id, 'origin' => 'S', 'status' => 'Confirmado', 'created_at' => now()]);
            DB::table('business_order_lines')->insert(['order_id' => $id, 'variant_id' => $this->variant, 'quantity' => 100 + $index, 'dispatched' => 0]);
        }

        $report = app(QueryPendingOrders::class)->handle($this->seller->id);
        $this->assertSame(206, $report['totals']['orders']);
        $this->assertSame(41413, $report['totals']['pairs']);
        $this->assertSame(41413, $report['customers']['items'][0]['pairs']);
    }

    public function test_pagination_keeps_complete_totals_and_resolves_catalog_and_clients_beyond_500_options(): void
    {
        for ($index = 1; $index <= 501; $index++) {
            DB::table('business_customers')->insert(['name' => 'Cliente '.$index, 'cuit' => (string) (30000000000 + $index), 'credit_history' => 'No mostrar']);
            DB::table('catalog_variants')->insert(['code' => 'META-'.$index, 'model' => 'Modelo '.$index, 'color' => 'Negro', 'size' => (string) $index]);
        }

        $lastCustomer = DB::table('business_customers')->max('id');
        $lastVariant = DB::table('catalog_variants')->max('id');

        for ($index = 0; $index < 27; $index++) {
            $this->order(2, customer: $lastCustomer - $index, variant: $lastVariant - $index);
        }

        $queries = app(QueryPendingOrders::class);
        $first = $queries->handle($this->admin->id);
        $second = $queries->handle($this->admin->id, null, 2, 2);
        $this->assertCount(25, $first['customers']['items']);
        $this->assertCount(2, $second['customers']['items']);
        $this->assertSame(['pairs' => 54, 'orders' => 27, 'articles' => 27], $second['totals']);
        $this->assertSame('Cliente 501', $second['customers']['items'][1]['customer']['name']);
        $this->assertSame('META-501', $second['articles']['items'][1]['article']['code']);
        $this->assertSame(2, $queries->handle($this->admin->id, null, 999, 999)['customers']['page']);
        $this->actingAs($this->admin)->get(route('pending.index', ['origin' => 'S', 'customer_page' => 2, 'article_page' => 2]))->assertOk()
            ->assertSee('META-501')->assertSee('Cliente 501')->assertSee('data-pending-pairs="54"', false)
            ->assertSee('origin=S', false)->assertSee('article_page=2', false)->assertSee('customer_page=2', false);
    }

    public function test_invalid_filters_and_inactive_accounts_are_rejected_without_any_write(): void
    {
        $this->actingAs($this->seller);

        foreach ([['origin' => ['S']], ['origin' => 'X'], ['customer_page' => -1], ['article_page' => 'bad']] as $filters) {
            $this->getJson(route('pending.snapshot', $filters))->assertUnprocessable();
        }

        DB::table('users')->where('id', $this->seller->id)->update(['is_active' => false]);
        $this->get(route('pending.snapshot'))->assertForbidden();
        $this->assertDatabaseCount('business_orders', 0);
        $this->expectException(PermissionDenied::class);
        app(QueryPendingOrders::class)->handle($this->seller->id);
    }
}
