<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\InventoryStore;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\OrderFulfillment;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Application\SearchTraceability;
use Simon\InventarioTrazabilidad\Application\TraceabilityRecords;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    private User $admin;

    private int $customer;

    private int $variant;

    private int $address;

    private int $order;

    private int $line;

    private int $batch;

    protected function setUp(): void
    {
        parent::setUp();
        RefreshDatabaseState::$migrated = false;
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        config(['inventory.audit_key' => str_repeat('traceability-test-only-key-', 3)]);
        $this->admin = User::factory()->create(['name' => 'Operador original']);
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente original', '20123456789', 'CREDITO-NO-PUBLICAR');
        $this->variant = app(PrepareInventory::class)->variant($this->admin->id, 'TRACE-01', 'Botín original', 'Negro', '40');
        $this->address = app(ManageAddresses::class)->save($this->admin->id, $this->customer, null, 'Destino original', 'Original 123', 'Posadas', 'Misiones');
        $this->order = app(RegisterOrder::class)->handle($this->admin->id, (string) Str::uuid(), $this->customer, 'F', [['variant_id' => $this->variant, 'quantity' => 1000]], $this->address);
        $this->line = DB::table('business_order_lines')->where('order_id', $this->order)->value('id');
        app(ManageCustomers::class)->authorize($this->admin->id, $this->order, true, true, 'CONDICIONES-NO-PUBLICAR');
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'LOTE-TRAZA', '2026-01-01', 2000);
        $this->batch = DB::table('inventory_batches')->value('id');
    }

    private function dispatch(int $quantity = 2, ?string $token = null, ?int $address = null, ?int $batch = null, bool $owner = false): int
    {
        return app(MoveStock::class)->dispatch($this->admin->id, $token ?? (string) Str::uuid(), $this->line, $batch ?? $this->batch, $quantity, $owner, $address);
    }

    private function legacy(bool $destination = true, ?string $token = null): int
    {
        return app(UnitOfWork::class)->run(function () use ($destination, $token): int {
            app(OrderFulfillment::class)->dispatch($this->line, 1);
            app(InventoryStore::class)->decrease($this->batch, 1);

            return app(InventoryStore::class)->record($token ?? (string) Str::uuid(), app(AccessPolicy::class)->require($this->admin->id, 'inventory.dispatch'), 'dispatch', 1, $this->batch, $this->line,
                ['kind' => 'dispatch', 'order_line_id' => $this->line, 'batch_id' => $this->batch, 'quantity' => 1, 'owner_withdrawal' => false], [], $destination ? ['label' => 'Destino legado', 'street' => 'Legado 456', 'locality' => 'Posadas', 'province' => 'Misiones'] : []);
        });
    }

    public function test_multiple_partial_deliveries_preserve_their_actual_destinations_after_order_cancellation(): void
    {
        $first = $this->dispatch();
        $alternate = app(ManageAddresses::class)->save($this->admin->id, $this->customer, null, 'Sucursal', 'Sucursal 999', 'Oberá', 'Misiones');
        $second = $this->dispatch(3, address: $alternate);
        app(ManageOrders::class)->transition($this->admin->id, (string) Str::uuid(), $this->order, 2, 'cancel', 'Cancelar el resto');
        $result = app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA');
        $this->assertSame(2, $result['total']);
        $this->assertSame([$second, $first], array_column($result['items'], 'id'));
        $this->assertSame([3, 2], array_column($result['items'], 'quantity'));
        $this->assertSame('Sucursal 999', $result['items'][0]['delivery_destination']['street']);
        $this->assertSame('Original 123', $result['items'][1]['delivery_destination']['street']);
        $this->assertSame('NP-000001', $result['items'][0]['order']['number']);
        $this->assertSame('Cliente original', $result['items'][0]['customer']['name']);
        $this->assertTrue($result['items'][0]['stored_identity']);
        $this->assertSame(3, app(SqlInventory::class)->verify()['sequence']);
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA']))->assertOk()
            ->assertSee('fi-main-sidebar', false)->assertSee('Sucursal 999')->assertSee('Original 123')
            ->assertDontSee('CREDITO-NO-PUBLICAR')->assertDontSee('CONDICIONES-NO-PUBLICAR');
    }

    public function test_new_signed_identities_and_batch_data_survive_changes_to_current_records(): void
    {
        $token = (string) Str::uuid();
        $id = $this->dispatch(token: $token);
        $payload = DB::table('inventory_audits')->where('movement_id', $id)->value('payload');
        app(ManageCustomers::class)->update($this->admin->id, $this->customer, 'Cliente renombrado', '20123456789', null, null);
        app(ManageAddresses::class)->save($this->admin->id, $this->customer, $this->address, 'Mudado', 'Actual 888', 'Posadas', 'Misiones', active: false);
        DB::table('catalog_variants')->where('id', $this->variant)->update(['code' => 'NUEVO-CODIGO', 'model' => 'Modelo renombrado']);
        $this->assertSame($id, $this->dispatch(token: $token));
        DB::table('inventory_batches')->where('id', $this->batch)->update(['lot' => 'LOTE-ACTUAL', 'manufactured_on' => '2026-02-01']);
        $result = app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA');
        $this->assertSame('Cliente original', $result['items'][0]['customer']['name']);
        $this->assertSame('TRACE-01', $result['items'][0]['article']['code']);
        $this->assertSame('Original 123', $result['items'][0]['delivery_destination']['street']);
        $this->assertSame('2026-01-01', $result['items'][0]['manufactured_on']);
        $this->assertSame(0, app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-ACTUAL')['total']);
        $this->assertSame($payload, DB::table('inventory_audits')->where('movement_id', $id)->value('payload'));
    }

    public function test_legacy_entries_keep_their_signatures_and_never_invent_a_historical_destination(): void
    {
        $first = $this->legacy();
        $second = $this->legacy(false);
        $before = DB::table('inventory_audits')->get()->toJson();
        $head = (array) DB::table('inventory_audit_head')->first();
        app(ManageCustomers::class)->update($this->admin->id, $this->customer, 'Nombre actual del legado', '20123456789', null, null);
        $report = app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA');
        $this->assertSame([$second, $first], array_column($report['items'], 'id'));
        $this->assertFalse($report['items'][0]['stored_identity']);
        $this->assertSame('Nombre actual del legado', $report['items'][0]['customer']['name']);
        $this->assertNull($report['items'][0]['delivery_destination']);
        $this->assertSame('Legado 456', $report['items'][1]['delivery_destination']['street']);
        $this->assertSame($before, DB::table('inventory_audits')->get()->toJson());
        $this->assertSame($head, (array) DB::table('inventory_audit_head')->first());
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA']))->assertOk()->assertSee('Referencia actual de cliente y artículo')->assertSee('Sin destino registrado');
    }

    public function test_exact_lot_and_inclusive_manufacturing_dates_combine_without_wildcard_injection(): void
    {
        $this->dispatch();
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, "LOTE%_O'HARA", '2026-02-01', 20);
        $batch = DB::table('inventory_batches')->where('lot', "LOTE%_O'HARA")->value('id');
        $id = $this->dispatch(batch: $batch);
        $queries = app(SearchTraceability::class);
        $this->assertSame([$id], array_column($queries->handle($this->admin->id, "LOTE%_O'HARA")['items'], 'id'));
        $this->assertSame(0, $queries->handle($this->admin->id, '%')['total']);
        $this->assertSame(1, $queries->handle($this->admin->id, null, '2026-02-01', '2026-02-01')['total']);
        $this->assertSame(2, $queries->handle($this->admin->id, null, '2026-01-01', '2026-02-01')['total']);
        $this->assertSame(0, $queries->handle($this->admin->id, 'LOTE-TRAZA', '2026-02-01')['total']);
        $this->assertSame(0, $queries->handle($this->admin->id, "' OR 1=1 --")['total']);
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'NO-EXISTE']))->assertOk()->assertSee('No se encontraron despachos');
    }

    public function test_search_is_not_limited_to_recent_audits_and_preserves_filters_when_paginating(): void
    {
        $first = $this->dispatch(1);

        for ($index = 0; $index < 26; $index++) {
            $this->dispatch(1);
        }

        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'OTRO-LOTE', '2026-03-01', 200);
        $other = DB::table('inventory_batches')->where('lot', 'OTRO-LOTE')->value('id');

        for ($index = 0; $index < 101; $index++) {
            $this->dispatch(1, batch: $other);
        }

        $result = app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA', '2026-01-01', '2026-01-01', 2);
        $this->assertSame(27, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertSame($first, $result['items'][1]['id']);
        $this->assertSame(2, app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA', page: 999)['page']);
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA', 'manufactured_from' => '2026-01-01']))
            ->assertOk()->assertSee('page=2', false)->assertSee('lot=LOTE-TRAZA', false)->assertSee('manufactured_from=2026-01-01', false);
    }

    public function test_permissions_protect_navigation_http_and_direct_application_queries(): void
    {
        $this->dispatch();

        foreach (['inventory.view', 'inventory.dispatch', 'audit.view', 'orders.manage', 'dispatch.authorize', 'orders.create', 'production.manage', 'inventory.receive', 'clients.manage'] as $permission) {
            $user = User::factory()->create();
            $role = DB::table('access_roles')->insertGetId(['name' => 'Trazabilidad '.$permission]);
            DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
            DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
            $response = $this->actingAs($user)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA']));

            if (in_array($permission, ['inventory.view', 'inventory.dispatch', 'audit.view', 'orders.manage', 'dispatch.authorize'], true)) {
                $response->assertOk()->assertSee('Cliente original');
                $this->get(route('filament.admin.pages.dashboard'))->assertOk()->assertSee(route('traceability.index'), false);
            } else {
                $response->assertForbidden();
                $this->get(route('filament.admin.pages.dashboard'))->assertOk()->assertDontSee(route('traceability.index'), false);

                try {
                    app(SearchTraceability::class)->handle($user->id, 'LOTE-TRAZA');
                    $this->fail('La consulta directa también exige permisos.');
                } catch (PermissionDenied) {
                    $this->assertDatabaseCount('inventory_movements', 2);
                }
            }
        }
    }

    public function test_invalid_filters_empty_search_and_invalid_batches_do_not_write_history(): void
    {
        $this->actingAs($this->admin);

        foreach ([['lot' => ['LOTE']], ['lot' => str_repeat('A', 101)], ['manufactured_from' => '2026-02-30'], ['page' => 0]] as $input) {
            $this->getJson(route('traceability.index', $input))->assertUnprocessable();
        }

        $this->get(route('traceability.index', ['manufactured_from' => '2026-03-01', 'manufactured_to' => '2026-01-01']))->assertUnprocessable()->assertSee('fecha inicial');
        $this->get(route('traceability.index'))->assertOk()->assertSee('Indicá al menos un criterio');

        foreach ([['lot' => ''], ['manufactured_on' => '2026-02-30']] as $invalid) {
            try {
                DB::table('inventory_batches')->where('id', $this->batch)->update(['lot' => 'LOTE-TRAZA', 'manufactured_on' => '2026-01-01', ...$invalid]);
                $this->dispatch();
                $this->fail('La salida requiere lote y fabricación válidos.');
            } catch (BusinessRuleViolation|QueryException $exception) {
                if ($exception instanceof QueryException) {
                    $this->assertSame(1292, $exception->errorInfo[1]);
                }

                $this->assertDatabaseCount('inventory_movements', 1);
                $this->assertDatabaseHas('business_order_lines', ['id' => $this->line, 'dispatched' => 0]);
            }
        }
    }

    public function test_corrupt_signed_payloads_fail_explicitly_instead_of_displaying_altered_data(): void
    {
        $id = $this->dispatch();
        DB::unprepared('DROP TRIGGER inventory_audits_no_update');
        $data = json_decode(DB::table('inventory_audits')->where('movement_id', $id)->value('payload'), true);
        $data['quantity'] = 9999;
        DB::table('inventory_audits')->where('movement_id', $id)->update(['payload' => json_encode($data)]);
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA']))
            ->assertUnprocessable()->assertSee('comprobación de integridad')->assertDontSee('data-trace-movement', false)->assertDontSee('No se encontraron despachos');
    }

    public function test_owner_withdrawals_are_distinguished_and_retries_do_not_duplicate_results(): void
    {
        $token = (string) Str::uuid();
        $id = $this->dispatch(token: $token, owner: true);
        $this->assertSame($id, $this->dispatch(token: $token, owner: true));
        $this->assertSame(1, app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA')['total']);
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA']))->assertOk()->assertSee('Retiro de propietarios');
    }

    public function test_empty_filters_do_not_scan_the_history_and_direct_invalid_dates_are_rejected(): void
    {
        $records = \Mockery::mock(TraceabilityRecords::class);
        $records->shouldNotReceive('search');
        $this->app->instance(TraceabilityRecords::class, $records);
        $this->assertSame(0, app(SearchTraceability::class)->handle($this->admin->id, '   ')['total']);
        $this->expectException(BusinessRuleViolation::class);
        app(SearchTraceability::class)->handle($this->admin->id, null, '2026-02-30');
    }

    public function test_legacy_retries_do_not_append_a_new_snapshot_or_change_an_old_signature(): void
    {
        $token = (string) Str::uuid();
        $id = $this->legacy(token: $token);
        $payload = DB::table('inventory_audits')->where('movement_id', $id)->value('payload');
        $this->assertArrayNotHasKey('shipment', json_decode($payload, true));
        $this->assertSame($id, $this->dispatch(1, $token));
        $this->assertSame($payload, DB::table('inventory_audits')->where('movement_id', $id)->value('payload'));
        $this->assertSame(1, app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA')['total']);
        $this->assertDatabaseHas('business_order_lines', ['id' => $this->line, 'dispatched' => 1]);
    }

    public function test_a_broken_previous_link_is_rejected_even_when_the_current_payload_was_not_changed(): void
    {
        $id = $this->dispatch();
        $this->dispatch();
        DB::unprepared('DROP TRIGGER inventory_audits_no_update');
        DB::table('inventory_audits')->where('movement_id', $id)->update(['digest' => str_repeat('f', 64)]);
        $this->expectException(BusinessRuleViolation::class);
        $this->expectExceptionMessage('comprobación de integridad');
        app(SearchTraceability::class)->handle($this->admin->id, 'LOTE-TRAZA');
    }

    public function test_malformed_audit_json_produces_an_explicit_error_and_does_not_report_a_false_empty_history(): void
    {
        $id = $this->dispatch();
        DB::unprepared('DROP TRIGGER inventory_audits_no_update');
        DB::table('inventory_audits')->where('movement_id', $id)->update(['payload' => '{invalid']);
        $this->actingAs($this->admin)->get(route('traceability.index', ['lot' => 'LOTE-TRAZA']))
            ->assertUnprocessable()->assertSee('No se pudo consultar el historial')->assertDontSee('No se encontraron despachos');
    }
}
