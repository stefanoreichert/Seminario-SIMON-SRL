<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ListOrders;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $customerId;

    private int $variantId;

    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.audit_key' => str_repeat('test-only-workflow-key-', 3)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->customerId = app(ManageCustomers::class)->register($this->admin->id, 'Cliente de estados', '20-12345678-9', 'Condiciones manuales');
        $this->variantId = app(PrepareInventory::class)->variant($this->admin->id, 'EST-01', 'Botín', 'Negro', '40');
        $this->addressId = app(ManageAddresses::class)->save($this->admin->id, $this->customerId, null, 'Central', 'Calle 123', 'Posadas', 'Misiones');
    }

    /** @return list<array{variant_id:int,quantity:int}> */
    private function lines(int $quantity = 10): array
    {
        return [['variant_id' => $this->variantId, 'quantity' => $quantity]];
    }

    private function order(bool $draft = false, int $quantity = 10, ?string $token = null): int
    {
        return app(RegisterOrder::class)->handle($this->admin->id, $token ?? (string) Str::uuid(), $this->customerId, 'F', $this->lines($quantity), $this->addressId, $draft);
    }

    private function change(int $id, string $action, int $revision = 0, ?string $token = null, ?string $reason = null): void
    {
        app(ManageOrders::class)->transition($this->admin->id, $token ?? (string) Str::uuid(), $id, $revision, $action, $reason);
    }

    private function batch(): int
    {
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variantId, 'LOTE-ESTADOS', '2026-01-01', 20);

        return DB::table('inventory_batches')->value('id');
    }

    private function authorize(int $id): void
    {
        app(ManageCustomers::class)->authorize($this->admin->id, $id, true, true, 'Autorización conservada');
    }

    public function test_draft_http_registration_replays_without_creating_pending_pairs_and_confirms_once(): void
    {
        $data = ['operation_id' => (string) Str::uuid(), 'customer_id' => $this->customerId, 'origin' => 'F', 'lines' => $this->lines(), 'intent' => 'draft'];
        $this->actingAs($this->admin)->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $this->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_orders', 1);
        $id = DB::table('business_orders')->value('id');
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'status' => 'Borrador']);
        $this->assertSame(0, app(ListOrders::class)->handle($this->admin->id)['lines'][0]->pending);
        $this->get(route('orders.show', $id))->assertOk()->assertSee('Editar borrador')->assertSee('Confirmar artículos guardados')->assertSee('Comercial');
        $token = (string) Str::uuid();
        $this->change($id, 'confirm', token: $token);
        $this->change($id, 'confirm', token: $token);
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'status' => 'Confirmado', 'revision' => 1]);
        $this->assertDatabaseCount('business_order_changes', 1);
        $this->assertSame(10, app(ListOrders::class)->handle($this->admin->id)['lines'][0]->pending);
        $this->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'status' => 'Confirmado']);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_draft_can_be_edited_with_atomic_lines_destination_and_idempotency(): void
    {
        $id = $this->order(true);
        $otherVariant = app(PrepareInventory::class)->variant($this->admin->id, 'EST-02', 'Zapato', 'Marrón', '41');
        $lines = [['variant_id' => $otherVariant, 'quantity' => 3], ...$this->lines(8)];
        $token = (string) Str::uuid();
        $case = app(ManageOrders::class);
        $case->saveDraft($this->admin->id, $token, $id, 0, $this->customerId, 'S', $lines, $this->addressId);
        $case->saveDraft($this->admin->id, $token, $id, 0, $this->customerId, 'S', $lines, $this->addressId);
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'origin' => 'S', 'revision' => 1, 'status' => 'Borrador']);
        $this->assertDatabaseCount('business_order_lines', 2);
        $this->assertDatabaseCount('business_order_changes', 1);
        $this->actingAs($this->admin)->get(route('orders.show', $id))->assertOk()->assertSee('EST-02')->assertSee('Calle 123');
        $this->assertSame(0, array_sum(array_column(app(ListOrders::class)->handle($this->admin->id)['lines'], 'pending')));
    }

    public function test_stale_edit_does_not_overwrite_another_edit_and_http_retains_the_form(): void
    {
        $id = $this->order(true);
        app(ManageOrders::class)->saveDraft($this->admin->id, (string) Str::uuid(), $id, 0, $this->customerId, 'F', $this->lines(8));
        $data = ['operation_id' => (string) Str::uuid(), 'revision' => 0, 'form_order_id' => $id, 'customer_id' => $this->customerId, 'origin' => 'F', 'lines' => $this->lines(7)];
        $this->actingAs($this->admin)->from(route('orders.show', $id))->put(route('orders.update', $id), $data)
            ->assertRedirect(route('orders.show', $id))->assertSessionHasErrors('operation')->assertSessionHasInput('lines.0.quantity', 7);
        $this->assertStringContainsString('Recargalo', session('errors')->first('operation'));
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('orders.show', $id))
            ->assertOk()->assertSee('Recargalo antes de continuar')->assertSee('name="revision" value="0"', false);
        $this->assertDatabaseHas('business_order_lines', ['order_id' => $id, 'quantity' => 8]);
        $this->assertDatabaseCount('business_order_changes', 1);
    }

    public function test_draft_confirmation_resolves_an_active_destination_and_preserves_it_on_retry(): void
    {
        $id = $this->order(true);
        $draft = $this->order(true, 9);
        $addresses = app(ManageAddresses::class);
        $addresses->save($this->admin->id, $this->customerId, $this->addressId, 'Central actualizada', 'Nueva 456', 'Posadas', 'Misiones');
        $token = (string) Str::uuid();
        $this->change($id, 'confirm', token: $token);
        $destination = DB::table('business_orders')->where('id', $id)->value('delivery_destination');
        $this->assertSame('Nueva 456', json_decode($destination, true)['street']);
        $addresses->save($this->admin->id, $this->customerId, $this->addressId, 'Inactiva', 'Otra 789', 'Posadas', 'Misiones', active: false);
        $this->change($id, 'confirm', token: $token);
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'delivery_destination' => $destination]);

        $this->expectException(BusinessRuleViolation::class);
        $this->change($draft, 'confirm');
    }

    public function test_invalid_customer_or_foreign_address_cannot_partially_replace_a_draft(): void
    {
        $id = $this->order(true);
        $otherCustomer = app(ManageCustomers::class)->register($this->admin->id, 'Otro cliente', '20-22345678-9', 'Manual');

        foreach ([[$otherCustomer, $this->addressId], [999999, null]] as [$customerId, $addressId]) {
            try {
                app(ManageOrders::class)->saveDraft($this->admin->id, (string) Str::uuid(), $id, 0, $customerId, 'S', $this->lines(5), $addressId);
                $this->fail('El borrador completo debe conservarse ante un cliente o destino inválido.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseHas('business_orders', ['id' => $id, 'customer_id' => $this->customerId, 'origin' => 'F', 'revision' => 0]);
                $this->assertDatabaseHas('business_order_lines', ['order_id' => $id, 'quantity' => 10]);
                $this->assertDatabaseCount('business_order_changes', 0);
            }
        }
    }

    public function test_http_draft_edits_save_all_fields_and_recover_invalid_rows(): void
    {
        $id = $this->order(true);
        $data = ['operation_id' => (string) Str::uuid(), 'revision' => 0, 'form_order_id' => $id, 'customer_id' => $this->customerId, 'delivery_address_id' => $this->addressId, 'origin' => 'G', 'lines' => $this->lines(6)];
        $this->actingAs($this->admin)->put(route('orders.update', $id), $data)->assertRedirect(route('orders.show', $id))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'origin' => 'G', 'revision' => 1]);
        $data = [...$data, 'operation_id' => (string) Str::uuid(), 'revision' => 1, 'lines' => $this->lines(0)];
        $this->from(route('orders.show', $id))->put(route('orders.update', $id), $data)->assertRedirect(route('orders.show', $id));
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('orders.show', $id))
            ->assertOk()->assertSee('La cantidad de la línea 1 debe ser mayor que cero.')->assertSee('value="0"', false);
        $this->assertDatabaseHas('business_order_lines', ['order_id' => $id, 'quantity' => 6]);
    }

    public function test_failed_history_insert_rolls_back_edit_and_line_replacement(): void
    {
        $id = $this->order(true);
        $original = DB::table('business_order_lines')->first();
        DB::unprepared("CREATE TRIGGER reject_order_change BEFORE INSERT ON business_order_changes BEGIN SELECT RAISE(ABORT, 'fallo simulado'); END");

        try {
            app(ManageOrders::class)->saveDraft($this->admin->id, (string) Str::uuid(), $id, 0, $this->customerId, 'S', $this->lines(8));
            $this->fail('El fallo del historial debe revertir todo el cambio.');
        } catch (QueryException) {
            $this->assertDatabaseHas('business_orders', ['id' => $id, 'origin' => 'F', 'revision' => 0]);
            $this->assertDatabaseHas('business_order_lines', ['id' => $original->id, 'quantity' => 10]);
            $this->assertDatabaseCount('business_order_changes', 0);
        }
    }

    public function test_partial_cancellation_preserves_stock_deliveries_authorization_credit_and_signed_history(): void
    {
        $id = $this->order();
        $this->authorize($id);
        $batch = $this->batch();
        $line = DB::table('business_order_lines')->value('id');
        $dispatchToken = (string) Str::uuid();
        app(MoveStock::class)->dispatch($this->admin->id, $dispatchToken, $line, $batch, 4);
        $audits = DB::table('inventory_audits')->get()->toJson();
        $token = (string) Str::uuid();
        $this->change($id, 'cancel', 1, $token, 'Cliente cancela los seis pares restantes');
        $this->change($id, 'cancel', 1, $token, 'Cliente cancela los seis pares restantes');
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'status' => 'Anulado', 'revision' => 2]);
        $this->assertDatabaseHas('business_order_lines', ['id' => $line, 'quantity' => 10, 'dispatched' => 4]);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batch, 'quantity' => 16]);
        $this->assertDatabaseHas('dispatch_authorizations', ['order_id' => $id, 'conditions' => 'Autorización conservada']);
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'credit_history' => 'Condiciones manuales']);
        $this->assertSame($audits, DB::table('inventory_audits')->get()->toJson());
        $this->assertSame(2, app(SqlInventory::class)->verify()['sequence']);
        $this->assertDatabaseCount('business_order_changes', 1);
        $this->assertSame(0, app(ListOrders::class)->handle($this->admin->id)['lines'][0]->pending);
        app(MoveStock::class)->dispatch($this->admin->id, $dispatchToken, $line, $batch, 4);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->actingAs($this->admin)->get(route('orders.show', $id))->assertOk()->assertSee('Cliente cancela los seis pares restantes')->assertDontSee('name="action"', false);

        try {
            app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), $line, $batch, 1);
            $this->fail('Una nota anulada no admite otro despacho.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('inventory_batches', ['id' => $batch, 'quantity' => 16]);
        }

        $this->assertNotSame($id, $this->order());
    }

    public function test_dispatch_invalidates_a_stale_cancellation_and_confirmed_lines_cannot_be_edited(): void
    {
        $id = $this->order();
        $this->authorize($id);
        $batch = $this->batch();
        $line = DB::table('business_order_lines')->value('id');
        app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), $line, $batch, 2);
        $this->actingAs($this->admin)->from(route('orders.show', $id))->post(route('orders.transition', $id), [
            'operation_id' => (string) Str::uuid(), 'revision' => 0, 'action' => 'cancel', 'reason' => 'Cancelación desde pantalla vieja',
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('business_orders', ['id' => $id, 'status' => 'Parcial']);

        try {
            app(ManageOrders::class)->saveDraft($this->admin->id, (string) Str::uuid(), $id, 1, $this->customerId, 'F', $this->lines(7));
            $this->fail('No se reescriben líneas con entregas.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_order_lines', ['id' => $line, 'quantity' => 10, 'dispatched' => 2]);
        }
    }

    public function test_drafts_cannot_be_dispatched_even_with_administrative_authorization(): void
    {
        $id = $this->order(true);
        $this->actingAs($this->admin)->postJson(route('operations.authorize'), ['order_id' => $id, 'billing' => true, 'credit' => true, 'conditions' => 'No autorizar un borrador'])
            ->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->assertDatabaseCount('dispatch_authorizations', 0);
        DB::table('dispatch_authorizations')->insert(['order_id' => $id, 'authorized_by' => $this->admin->id, 'billing_validated' => true, 'credit_validated' => true, 'conditions' => 'Autorización obsoleta simulada', 'updated_at' => now()]);
        $batch = $this->batch();
        $line = DB::table('business_order_lines')->value('id');
        $this->actingAs($this->admin)->get(route('operations.dispatch.index'))->assertOk()->assertDontSee('value="'.$line.'" data-address-url', false);

        try {
            app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), $line, $batch, 1);
            $this->fail('El borrador no permite salidas.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_order_lines', ['id' => $line, 'dispatched' => 0]);
            $this->assertDatabaseCount('inventory_movements', 1);
        }
    }

    public function test_duplicates_are_rejected_across_origins_and_line_order_but_retries_are_accepted(): void
    {
        $variant = app(PrepareInventory::class)->variant($this->admin->id, 'EST-03', 'Zapato', 'Azul', '42');
        $lines = [...$this->lines(), ['variant_id' => $variant, 'quantity' => 4]];
        $token = (string) Str::uuid();
        $case = app(RegisterOrder::class);
        $id = $case->handle($this->admin->id, $token, $this->customerId, 'F', $lines);
        $this->assertSame($id, $case->handle($this->admin->id, $token, $this->customerId, 'F', $lines));

        try {
            $case->handle($this->admin->id, (string) Str::uuid(), $this->customerId, 'S', array_reverse($lines), $this->addressId, true);
            $this->fail('Una operación nueva no puede repetir una nota activa idéntica.');
        } catch (BusinessRuleViolation $exception) {
            $this->assertStringContainsString('NP-000001', $exception->getMessage());
            $this->assertDatabaseCount('business_orders', 1);
        }
    }

    public function test_editing_a_draft_cannot_duplicate_another_active_order(): void
    {
        $this->order();
        $id = $this->order(true, 9);
        $this->expectException(BusinessRuleViolation::class);
        $this->expectExceptionMessage('Ya existe el pedido activo');
        app(ManageOrders::class)->saveDraft($this->admin->id, (string) Str::uuid(), $id, 0, $this->customerId, 'G', $this->lines());
    }

    public function test_delivered_orders_allow_new_purchases_and_reject_cancellation(): void
    {
        $id = $this->order();
        $this->authorize($id);
        $batch = $this->batch();
        app(MoveStock::class)->dispatch($this->admin->id, (string) Str::uuid(), DB::table('business_order_lines')->value('id'), $batch, 10);
        $this->assertNotSame($id, $this->order());
        $this->expectException(BusinessRuleViolation::class);
        $this->change($id, 'cancel', 1, reason: 'No debe anular una entrega completa');
    }

    public function test_cancellation_requires_a_reason_and_cannot_be_reopened_or_reuse_another_action_token(): void
    {
        $id = $this->order(true);
        $this->actingAs($this->admin)->from(route('orders.show', $id))->post(route('orders.transition', $id), [
            'operation_id' => (string) Str::uuid(), 'revision' => 0, 'action' => 'cancel',
        ])->assertSessionHasErrors('reason');
        $token = (string) Str::uuid();
        $this->change($id, 'cancel', token: $token, reason: 'Error de carga');

        foreach ([['confirm', 1, (string) Str::uuid()], ['confirm', 0, $token], ['cancel', 1, (string) Str::uuid()]] as [$action, $revision, $operation]) {
            try {
                $this->change($id, $action, $revision, $operation, 'Error de carga');
                $this->fail('No puede reabrirse ni reutilizar otra acción.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseHas('business_orders', ['id' => $id, 'status' => 'Anulado', 'revision' => 1]);
            }
        }
    }

    public function test_sellers_can_change_only_their_own_orders_and_readers_cannot_write(): void
    {
        $seller = User::factory()->create(['sales_identifier' => 'S']);
        $reader = User::factory()->create();

        foreach ([[$seller, 'orders.create'], [$reader, 'inventory.view']] as [$user, $permission]) {
            $role = DB::table('access_roles')->insertGetId(['name' => 'Estado '.Str::uuid()]);
            DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
            DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
        }

        $id = app(RegisterOrder::class)->handle($seller->id, (string) Str::uuid(), $this->customerId, 'S', $this->lines(), null, true);
        app(ManageOrders::class)->saveDraft($seller->id, (string) Str::uuid(), $id, 0, $this->customerId, 'S', $this->lines(8));
        $foreign = $this->order(true);
        $this->actingAs($seller)->get(route('orders.show', $foreign))->assertForbidden();
        $this->post(route('orders.transition', $foreign), ['operation_id' => (string) Str::uuid(), 'revision' => 0, 'action' => 'confirm'])->assertForbidden();
        $this->actingAs($reader)->get(route('orders.show', $id))->assertOk()->assertDontSee('name="action"', false);
        $this->post(route('orders.transition', $id), ['operation_id' => (string) Str::uuid(), 'revision' => 1, 'action' => 'confirm'])->assertForbidden();

        try {
            app(ManageOrders::class)->transition($seller->id, (string) Str::uuid(), $foreign, 0, 'confirm');
            $this->fail('El núcleo también debe rechazar la suplantación.');
        } catch (PermissionDenied) {
            $this->assertDatabaseHas('business_orders', ['id' => $foreign, 'status' => 'Borrador']);
        }
    }
}
