<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\InventarioTrazabilidad\Application\ManageSupplies;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PairUnits;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlPairUnits;
use Simon\InventarioTrazabilidad\UI\PickingSelection;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class PickingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $variant;

    private int $batch;

    private int $order;

    private int $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
        config(['inventory.audit_key' => str_repeat('picking-test-signing-key-', 3)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->variant = app(PrepareInventory::class)->variant($this->admin->id, 'PAR-ART', 'Botín', 'Negro', '40');
        $customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente picking', '20123456789', 'No es un cobro');
        $address = app(ManageAddresses::class)->save($this->admin->id, $customer, null, 'Depósito', 'Calle 1', 'Posadas', 'Misiones');
        $this->order = app(RegisterOrder::class)->handle($this->admin->id, (string) Str::uuid(), $customer, 'F', [['variant_id' => $this->variant, 'quantity' => 2]], $address);
        $this->line = (int) DB::table('business_order_lines')->where('order_id', $this->order)->value('id');
        $movement = app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variant, 'LOTE-COMPARTIDO', '2026-01-01', 4);
        $this->batch = (int) DB::table('inventory_movements')->where('id', $movement)->value('batch_id');
    }

    /** @return list<string> */
    private function identify(): array
    {
        app(ManagePicking::class)->identify($this->admin->id, (string) Str::uuid(), $this->batch);

        return DB::table('inventory_pair_units')->where('batch_id', $this->batch)->orderBy('code')->pluck('code')->all();
    }

    public function test_codes_are_globally_unique_even_with_a_shared_lot_and_retries_do_not_reidentify(): void
    {
        $token = (string) Str::uuid();
        $first = app(ManagePicking::class)->identify($this->admin->id, $token, $this->batch);
        $this->assertSame($first, app(ManagePicking::class)->identify($this->admin->id, $token, $this->batch));
        $codes = DB::table('inventory_pair_units')->pluck('code')->all();
        $this->assertCount(4, array_unique($codes));
        $this->assertDatabaseHas('inventory_batches', ['id' => $this->batch, 'quantity' => 4]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->expectException(QueryException::class);
        DB::table('inventory_pair_units')->insert(['code' => $codes[0], 'batch_id' => $this->batch, 'state' => 'available']);
    }

    public function test_scan_unknown_duplicate_wrong_variant_and_overpicking_are_rejected_without_extra_pairs(): void
    {
        $codes = $this->identify();
        $picking = app(ManagePicking::class);
        $token = (string) Str::uuid();
        $id = $picking->scan($this->admin->id, $token, $this->line, $codes[0]);
        $this->assertSame($id, $picking->scan($this->admin->id, $token, $this->line, $codes[0]));

        foreach ([$codes[0], 'CODIGO-DESCONOCIDO'] as $code) {
            try {
                $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $code);
                $this->fail('No debe aceptarse una lectura duplicada o desconocida.');
            } catch (BusinessRuleViolation) {
                $this->assertSame(1, DB::table('inventory_pair_units')->where('state', 'picked')->count());
            }
        }

        $other = app(PrepareInventory::class)->variant($this->admin->id, 'PAR-OTRO', 'Botín', 'Negro', '41');
        $movement = app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $other, 'LOTE-COMPARTIDO', '2026-01-01', 1);
        $batch = (int) DB::table('inventory_movements')->where('id', $movement)->value('batch_id');
        $picking->identify($this->admin->id, (string) Str::uuid(), $batch);
        $otherCode = DB::table('inventory_pair_units')->where('batch_id', $batch)->value('code');

        try {
            $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $otherCode);
            $this->fail('No se puede seleccionar otro talle.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('inventory_pair_units', ['code' => $otherCode, 'state' => 'available']);
        }

        $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $codes[1]);
        $this->expectException(BusinessRuleViolation::class);
        $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $codes[2]);
    }

    public function test_dispatch_requires_authorization_and_exact_selected_codes_and_signs_their_identity(): void
    {
        $codes = array_slice($this->identify(), 0, 2);

        foreach ($codes as $code) {
            app(ManagePicking::class)->scan($this->admin->id, (string) Str::uuid(), $this->line, $code);
        }

        try {
            app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), $this->line, $this->batch, 2, pairCodes: $codes);
            $this->fail('La identificación no sustituye la validación administrativa.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('inventory_batches', ['id' => $this->batch, 'quantity' => 4]);
            $this->assertSame(2, DB::table('inventory_pair_units')->where('state', 'picked')->count());
        }

        app(ManageCustomers::class)->authorize($this->admin->id, $this->order, true, true, 'Autorizado para picking');
        $token = (string) Str::uuid();
        $movement = app(MoveStock::class)->dispatch($this->admin->id, $token, $this->line, $this->batch, 2, pairCodes: $codes);
        $this->assertSame($movement, app(MoveStock::class)->dispatch($this->admin->id, $token, $this->line, $this->batch, 2, pairCodes: array_reverse($codes)));
        $this->assertDatabaseHas('inventory_batches', ['id' => $this->batch, 'quantity' => 2]);
        $this->assertDatabaseHas('business_orders', ['id' => $this->order, 'status' => 'Entregado']);
        $this->assertSame(2, DB::table('inventory_pair_units')->where('movement_id', $movement)->where('state', 'dispatched')->count());
        $payload = json_decode(DB::table('inventory_audits')->where('movement_id', $movement)->value('payload'), true);
        sort($codes);
        $this->assertSame($codes, $payload['shipment']['pair_codes']);
        $this->assertSame(2, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_tracked_stock_cannot_bypass_selection_and_release_makes_the_same_pair_available_again(): void
    {
        $codes = $this->identify();
        app(ManageCustomers::class)->authorize($this->admin->id, $this->order, true, true, 'Autorizado');

        try {
            app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), $this->line, $this->batch, 1);
            $this->fail('Un lote identificado requiere código individual.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('inventory_batches', ['id' => $this->batch, 'quantity' => 4]);
        }

        $picking = app(ManagePicking::class);
        $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $codes[0]);
        $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $codes[0], true);
        $this->assertDatabaseHas('inventory_pair_units', ['code' => $codes[0], 'state' => 'available', 'order_line_id' => null]);
        $picking->scan($this->admin->id, (string) Str::uuid(), $this->line, $codes[0]);
        $this->assertDatabaseHas('inventory_pair_units', ['code' => $codes[0], 'state' => 'picked']);
    }

    public function test_new_receipts_to_a_tracked_batch_get_new_codes_once_and_audit_failures_roll_everything_back(): void
    {
        $this->identify();
        $production = app(PrepareInventory::class)->production($this->admin->id, (string) Str::uuid(), $this->variant, 'LOTE-COMPARTIDO', '2026-01-01', 3);
        $token = (string) Str::uuid();
        app(MoveStock::class)->receive($this->admin->id, $token, $production, 2);
        app(MoveStock::class)->receive($this->admin->id, $token, $production, 2);
        $this->assertDatabaseCount('inventory_pair_units', 6);
        $this->assertDatabaseHas('inventory_batches', ['id' => $this->batch, 'quantity' => 6]);
        config(['inventory.audit_key' => null]);

        try {
            app(MoveStock::class)->receive($this->admin->id, (string) Str::uuid(), $production, 1);
            $this->fail('Un fallo de auditoría debe revertir también los códigos nuevos.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseCount('inventory_pair_units', 6);
            $this->assertDatabaseHas('production_batches', ['id' => $production, 'in_process' => 1]);
        }
    }

    public function test_a_pick_operation_failure_rolls_back_the_pair_and_coordinator(): void
    {
        $codes = $this->identify();
        $before = (array) DB::table('inventory_audit_head')->first();
        $store = new SqlPairUnits;
        $pairs = Mockery::mock(PairUnits::class);
        $pairs->shouldReceive('replay')->andReturnUsing($store->replay(...));
        $pairs->shouldReceive('get')->andReturnUsing($store->get(...));
        $pairs->shouldReceive('pickedCount')->andReturnUsing($store->pickedCount(...));
        $pairs->shouldReceive('save')->andReturnUsing($store->save(...));
        $pairs->shouldReceive('record')->andThrow(new BusinessRuleViolation('Fallo al guardar lectura'));
        $this->app->instance(PairUnits::class, $pairs);

        try {
            app(ManagePicking::class)->scan($this->admin->id, (string) Str::uuid(), $this->line, $codes[0]);
            $this->fail('Una lectura sin registro debe revertirse.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('inventory_pair_units', ['code' => $codes[0], 'state' => 'available']);
            $this->assertSame($before, (array) DB::table('inventory_audit_head')->first());
        }
    }

    public function test_native_screens_and_direct_writes_enforce_existing_role_permissions(): void
    {
        $this->identify();
        $this->actingAs($this->admin)->get(route('picking.index', ['line_id' => $this->line]))->assertOk()->assertSee('fi-main-sidebar', false)->assertSee('data-pair-code', false)->assertSee('PAR-');
        $this->get(route('supplies.index'))->assertOk()->assertSee('Insumos y vencimientos')->assertSee('Guardar anticipación');
        $user = User::factory()->create();
        $role = DB::table('access_roles')->insertGetId(['name' => 'Solo consulta picking']);
        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => 'inventory.view']);
        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
        $this->actingAs($user)->get(route('picking.index', ['line_id' => $this->line]))->assertOk()->assertDontSee('data-pair-code', false);
        $this->post(route('picking.identify'), [])->assertForbidden();
        $this->post(route('picking.scan'), [])->assertForbidden();
        $this->post(route('picking.dispatch'), [])->assertForbidden();
        $this->post(route('supplies.store'), [])->assertForbidden();
        $this->post(route('supplies.configure'), [])->assertForbidden();
    }

    public function test_livewire_picking_scans_releases_and_rejects_duplicate_codes(): void
    {
        $codes = $this->identify();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(PickingSelection::class, ['lineId' => $this->line, 'batchId' => null, 'page' => 1]);
        $component->set('code', $codes[0])->call('scan')->assertHasNoErrors()->assertSee('Par leído y seleccionado');
        $this->assertDatabaseHas('inventory_pair_units', ['code' => $codes[0], 'state' => 'picked', 'order_line_id' => $this->line]);
        $component->set('code', $codes[0])->call('scan')->assertHasErrors('code');
        $this->assertSame(1, DB::table('inventory_pair_units')->where('state', 'picked')->count());
        $component->call('release', $codes[0])->assertHasNoErrors()->assertSee('Par retirado');
        $this->assertDatabaseHas('inventory_pair_units', ['code' => $codes[0], 'state' => 'available', 'order_line_id' => null]);
    }

    public function test_livewire_picking_filters_and_can_clear_the_query(): void
    {
        $this->identify();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PickingSelection::class, ['lineId' => null, 'batchId' => null, 'page' => 1])
            ->set('lineInput', (string) $this->line)->set('batchInput', (string) $this->batch)
            ->call('search')->assertSet('lineId', $this->line)->assertSet('batchId', $this->batch)->assertSee('data-pair-code', false)
            ->set('lineInput', '')->set('batchInput', '')
            ->call('search')->assertSet('lineId', null)->assertSet('batchId', null)->assertDontSee('data-pair-code', false);
    }

    public function test_livewire_picking_rechecks_permissions_after_mount(): void
    {
        $codes = $this->identify();
        $user = User::factory()->create();
        $role = DB::table('access_roles')->insertGetId(['name' => 'Despacho temporal']);
        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => 'inventory.dispatch']);
        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(PickingSelection::class, ['lineId' => $this->line, 'batchId' => null, 'page' => 1]);
        $component->set('code', $codes[0]);
        DB::table('access_role_permissions')->where('role_id', $role)->delete();
        $component->call('scan')->assertForbidden();
        $this->assertDatabaseHas('inventory_pair_units', ['code' => $codes[0], 'state' => 'available']);
    }

    public function test_expiry_boundaries_preserve_the_supply_and_do_not_block_a_related_receipt(): void
    {
        $production = app(PrepareInventory::class)->production($this->admin->id, (string) Str::uuid(), $this->variant, 'TANDA-INSUMO', '2026-01-01', 2);
        $supplies = app(ManageSupplies::class);
        $supplies->configure($this->admin->id, 30);

        foreach (['2026-11-10', '2026-11-11', '2026-12-11', '2026-12-12'] as $index => $date) {
            $token = (string) Str::uuid();
            $id = $supplies->register($this->admin->id, $token, 'Insumo '.$index, 'LOTE-'.$index, $date, $production);
            $this->assertSame($id, $supplies->register($this->admin->id, $token, 'Insumo '.$index, 'LOTE-'.$index, $date, $production));
        }

        $report = $supplies->overview($this->admin->id);
        $this->assertSame(['Vencido', 'Vence hoy', 'Próximo a vencer', 'Vigente'], array_column($report['items'], 'warning'));
        $this->assertSame([$production, $production, $production, $production], array_column($report['items'], 'production_id'));
        app(MoveStock::class)->receive($this->admin->id, (string) Str::uuid(), $production, 1);
        $this->assertDatabaseHas('production_batches', ['id' => $production, 'in_process' => 1]);
        $this->actingAs($this->admin)->get(route('supplies.index'))->assertOk()->assertSee('Próximo a vencer')->assertSee('Vencido');
        $this->assertDatabaseCount('inventory_supply_lots', 4);
    }

    public function test_expired_supplies_warn_even_before_configuration_and_invalid_links_are_rejected(): void
    {
        $supplies = app(ManageSupplies::class);
        $supplies->register($this->admin->id, (string) Str::uuid(), 'Adhesivo', 'SIN-TANDA', '2026-11-10');
        $this->assertSame('Vencido', $supplies->overview($this->admin->id)['items'][0]['warning']);
        $this->expectException(BusinessRuleViolation::class);
        $supplies->register($this->admin->id, (string) Str::uuid(), 'Adhesivo', 'OTRO', '2026-11-20', 999999);
    }
}
