<?php

namespace Tests\Feature;

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
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class CustomerAddressesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $warehouse;

    private User $unauthorized;

    private int $customerId;

    private int $otherCustomerId;

    private int $variantId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory.audit_key' => str_repeat('address-test-audit-key-', 3)]);
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->seller = User::factory()->create(['sales_identifier' => 'S']);
        $this->warehouse = User::factory()->create();
        $this->unauthorized = User::factory()->create();
        $this->grant($this->seller, ['orders.create']);
        $this->grant($this->warehouse, ['inventory.view', 'inventory.dispatch']);
        $customers = app(ManageCustomers::class);
        $this->customerId = $customers->register($this->admin->id, 'Cliente con sucursales', '20-12345678-9', 'Crédito privado del primer cliente');
        $this->otherCustomerId = $customers->register($this->admin->id, 'Otro cliente', '30-87654321-0', 'Crédito privado del otro cliente');
        $this->variantId = app(PrepareInventory::class)->variant($this->admin->id, 'ADDRESS-01', 'Botín', 'Negro', '40');
    }

    /** @param list<string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $roleId = DB::table('access_roles')->insertGetId(['name' => 'Rol direcciones '.Str::uuid()]);

        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }

        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);
    }

    private function address(int $customerId, string $label = 'Casa central', string $street = 'Calle Original 123', bool $active = true): int
    {
        return app(ManageAddresses::class)->save(
            $this->admin->id, $customerId, null, $label, $street, 'Posadas', 'Misiones', '3300', 'Argentina', 'Acceso por puerta principal', $active,
        );
    }

    /** @return array{label: string, street: string, locality: string, province: string, postal_code: string, country: string, references: string, active: string} */
    private function fields(string $label = 'Sucursal', string $street = 'Calle Secundaria 456'): array
    {
        return [
            'label' => $label, 'street' => $street, 'locality' => 'Posadas', 'province' => 'Misiones',
            'postal_code' => '3300', 'country' => 'Argentina', 'references' => 'Portón azul', 'active' => '1',
        ];
    }

    private function order(?int $addressId = null, ?string $token = null): int
    {
        return app(RegisterOrder::class)->handle(
            $this->seller->id, $token ?? (string) Str::uuid(), $this->customerId, 'S', [['variant_id' => $this->variantId, 'quantity' => 10]], $addressId,
        );
    }

    private function line(int $orderId): int
    {
        return DB::table('business_order_lines')->where('order_id', $orderId)->value('id');
    }

    private function stock(): int
    {
        $movementId = app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variantId, 'LOTE-DIRECCIONES', '2026-01-01', 20);

        return DB::table('inventory_movements')->where('id', $movementId)->value('batch_id');
    }

    private function authorize(int $orderId): void
    {
        app(ManageCustomers::class)->authorize($this->admin->id, $orderId, true, true, 'Despacho autorizado por administración');
    }

    /** @return array<string, mixed> */
    private function destination(int $orderId): array
    {
        return json_decode(DB::table('business_orders')->where('id', $orderId)->value('delivery_destination'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_multiple_addresses_can_be_created_consulted_and_edited_without_touching_other_destinations(): void
    {
        $this->actingAs($this->admin)->get(route('customers.addresses.create', $this->customerId))->assertOk();

        foreach ([$this->fields('Casa central', 'Primera 123'), $this->fields('Sucursal norte', 'Segunda 456')] as $fields) {
            $this->from(route('customers.addresses.create', $this->customerId))
                ->post(route('customers.addresses.store', $this->customerId), $fields)->assertSessionHasNoErrors();
        }

        $addresses = app(ManageAddresses::class)->all($this->admin->id, $this->customerId);
        $this->assertCount(2, $addresses);
        $first = collect($addresses)->firstWhere('label', 'Casa central');
        $second = collect($addresses)->firstWhere('label', 'Sucursal norte');
        $otherId = $this->address($this->otherCustomerId, 'Otro domicilio', 'Tercera 789');
        $this->get(route('customers.show', $this->customerId))->assertOk()->assertSee('Casa central')->assertSee('Sucursal norte')->assertDontSee('Otro domicilio');
        $this->get(route('customers.addresses.edit', [$this->customerId, $first['id']]))->assertOk()->assertSee('Primera 123');
        $this->from(route('customers.addresses.edit', [$this->customerId, $first['id']]))
            ->put(route('customers.addresses.update', [$this->customerId, $first['id']]), $this->fields('Central actualizada', 'Primera 999'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_delivery_addresses', ['id' => $first['id'], 'customer_id' => $this->customerId, 'label' => 'Central actualizada', 'street' => 'Primera 999']);
        $this->assertDatabaseHas('customer_delivery_addresses', ['id' => $second['id'], 'street' => 'Segunda 456']);
        $this->assertDatabaseHas('customer_delivery_addresses', ['id' => $otherId, 'customer_id' => $this->otherCustomerId, 'street' => 'Tercera 789']);
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'credit_history' => 'Crédito privado del primer cliente']);
        $this->assertDatabaseCount('customer_credit_entries', 2);
    }

    public function test_address_ownership_is_checked_for_direct_edits_and_http_requests(): void
    {
        $addressId = $this->address($this->customerId);
        $addresses = app(ManageAddresses::class);
        $this->assertNull($addresses->find($this->admin->id, $this->otherCustomerId, $addressId));
        $this->actingAs($this->admin)->get(route('customers.addresses.edit', [$this->otherCustomerId, $addressId]))->assertNotFound();
        $this->from(route('customers.show', $this->otherCustomerId))
            ->put(route('customers.addresses.update', [$this->otherCustomerId, $addressId]), [
                ...$this->fields('Intento ajeno'), 'customer_id' => $this->customerId,
            ])->assertSessionHasErrors('operation');

        try {
            $addresses->save($this->admin->id, $this->otherCustomerId, $addressId, 'Intento directo', 'Otra 100', 'Posadas', 'Misiones');
            $this->fail('Editar una dirección de otro cliente debe rechazarse.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('customer_delivery_addresses', ['id' => $addressId, 'customer_id' => $this->customerId, 'label' => 'Casa central', 'street' => 'Calle Original 123']);
        }

        $this->assertDatabaseCount('customer_delivery_addresses', 1);
    }

    public function test_options_are_customer_scoped_and_do_not_expose_credit_to_sellers_or_warehouse(): void
    {
        $activeId = $this->address($this->customerId);
        $this->address($this->customerId, 'Dirección inactiva', 'Antigua 123', false);
        $this->address($this->otherCustomerId, 'Dirección de otro cliente');

        foreach ([$this->seller, $this->warehouse, $this->admin] as $user) {
            $this->actingAs($user)->getJson(route('customers.addresses.options', $this->customerId))->assertOk()
                ->assertJsonCount(1)->assertJsonPath('0.id', $activeId)->assertJsonPath('0.customer_id', $this->customerId)
                ->assertDontSee('Crédito privado')->assertDontSee('Dirección de otro cliente')->assertDontSee('Dirección inactiva');
        }

        $this->assertCount(2, app(ManageAddresses::class)->all($this->admin->id, $this->customerId));
    }

    public function test_address_management_and_option_queries_enforce_permissions(): void
    {
        $addressId = $this->address($this->customerId);
        $this->actingAs($this->unauthorized)->getJson(route('customers.addresses.options', $this->customerId))->assertForbidden();

        foreach ([$this->seller, $this->warehouse, $this->unauthorized] as $user) {
            $this->actingAs($user)->get(route('customers.addresses.create', $this->customerId))->assertForbidden();
            $this->get(route('customers.addresses.edit', [$this->customerId, $addressId]))->assertForbidden();
            $this->post(route('customers.addresses.store', $this->customerId), $this->fields())->assertForbidden();
            $this->put(route('customers.addresses.update', [$this->customerId, $addressId]), $this->fields())->assertForbidden();
        }

        $addresses = app(ManageAddresses::class);

        foreach ([
            fn () => $addresses->all($this->seller->id, $this->customerId),
            fn () => $addresses->find($this->unauthorized->id, $this->customerId, $addressId),
            fn () => $addresses->options($this->unauthorized->id, $this->customerId),
            fn () => $addresses->save($this->seller->id, $this->customerId, null, 'Sin permiso', 'Calle 1', 'Posadas', 'Misiones'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('El caso de uso debe comprobar permisos también sin HTTP.');
            } catch (PermissionDenied) {
                $this->assertDatabaseCount('customer_delivery_addresses', 1);
            }
        }

        DB::table('users')->where('id', $this->seller->id)->update(['is_active' => false]);
        $this->actingAs($this->seller)->getJson(route('customers.addresses.options', $this->customerId))->assertForbidden();
    }

    public function test_address_validation_runs_on_http_and_direct_calls(): void
    {
        $this->actingAs($this->admin)->from(route('customers.addresses.create', $this->customerId));

        foreach ([['label', ''], ['street', ''], ['locality', ''], ['province', ''], ['label', str_repeat('a', 101)], ['street', str_repeat('a', 256)], ['postal_code', str_repeat('1', 21)], ['references', str_repeat('a', 1001)]] as [$field, $value]) {
            $this->post(route('customers.addresses.store', $this->customerId), [...$this->fields(), $field => $value])->assertSessionHasErrors($field);
        }

        foreach ([
            fn () => app(ManageAddresses::class)->save($this->admin->id, $this->customerId, null, '  ', 'Calle 1', 'Posadas', 'Misiones'),
            fn () => app(ManageAddresses::class)->save($this->admin->id, $this->customerId, null, 'Central', ' ', 'Posadas', 'Misiones'),
            fn () => app(ManageAddresses::class)->save($this->admin->id, 999999, null, 'Inexistente', 'Calle 1', 'Posadas', 'Misiones'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Los domicilios inválidos o sin cliente deben rechazarse.');
            } catch (BusinessRuleViolation $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        $this->assertDatabaseCount('customer_delivery_addresses', 0);
    }

    public function test_orders_reject_foreign_or_inactive_destinations_without_partial_registration(): void
    {
        $foreignId = $this->address($this->otherCustomerId);
        $inactiveId = $this->address($this->customerId, 'Viejo domicilio', 'Antigua 456', false);

        foreach ([$foreignId, $inactiveId] as $addressId) {
            try {
                $this->order($addressId);
                $this->fail('El pedido no puede tomar una dirección ajena o inactiva.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseCount('business_orders', 0);
                $this->assertDatabaseCount('business_order_lines', 0);
            }
        }

        $this->actingAs($this->seller)->from(route('operations.index'))->post(route('operations.order'), [
            'operation_id' => (string) Str::uuid(), 'customer_id' => $this->customerId, 'origin' => 'S',
            'lines' => [['variant_id' => $this->variantId, 'quantity' => 10]], 'delivery_address_id' => $foreignId,
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('business_orders', 0);
    }

    public function test_order_destination_and_retry_survive_address_edits_and_inactivation(): void
    {
        $addressId = $this->address($this->customerId);
        $token = (string) Str::uuid();
        $orderId = $this->order($addressId, $token);
        $before = $this->destination($orderId);
        $this->assertSame($addressId, $before['id']);
        $this->assertSame($this->customerId, $before['customer_id']);
        $this->assertSame('Calle Original 123', $before['street']);
        app(ManageAddresses::class)->save($this->admin->id, $this->customerId, $addressId, 'Central mudada', 'Nueva 999', 'Posadas', 'Misiones', active: false);
        $this->assertSame($orderId, $this->order($addressId, $token));
        $this->assertSame($before, $this->destination($orderId));
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 1);
        $this->actingAs($this->seller)->get(route('operations.index'))->assertOk()->assertSee('Calle Original 123')->assertDontSee('Crédito privado');
        $differentId = $this->address($this->customerId, 'Sucursal nueva', 'Nueva 777');

        try {
            $this->order($differentId, $token);
            $this->fail('Un token no puede cambiar el destino de un pedido ya confirmado.');
        } catch (BusinessRuleViolation) {
            $this->assertSame($before, $this->destination($orderId));
            $this->assertDatabaseCount('business_orders', 1);
        }
    }

    public function test_dispatch_keeps_the_order_snapshot_and_can_explicitly_select_another_customer_destination(): void
    {
        $originalId = $this->address($this->customerId);
        $alternateId = $this->address($this->customerId, 'Sucursal alternativa', 'Alternativa 500');
        $orderId = $this->order($originalId);
        $snapshot = $this->destination($orderId);
        $this->authorize($orderId);
        $batchId = $this->stock();
        app(ManageAddresses::class)->save($this->admin->id, $this->customerId, $originalId, 'Dirección actual', 'Nueva 999', 'Posadas', 'Misiones', active: false);
        app(MoveStock::class)->dispatch($this->warehouse->id, (string) Str::uuid(), $this->line($orderId), $batchId, 3);
        $firstAudit = DB::table('inventory_audits')->orderByDesc('sequence')->first();
        $firstPayload = json_decode($firstAudit->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($snapshot, $firstPayload['delivery_destination']);
        app(MoveStock::class)->dispatch($this->warehouse->id, (string) Str::uuid(), $this->line($orderId), $batchId, 2, false, $alternateId);
        $secondPayload = json_decode(DB::table('inventory_audits')->orderByDesc('sequence')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($alternateId, $secondPayload['delivery_destination']['id']);
        $this->assertSame('Alternativa 500', $secondPayload['delivery_destination']['street']);
        $this->assertSame($snapshot, $this->destination($orderId));
        $this->assertSame($firstAudit->payload, DB::table('inventory_audits')->where('sequence', $firstAudit->sequence)->value('payload'));
        $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 5]);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 15]);
        $this->assertSame(3, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_dispatch_without_a_destination_or_with_an_invalid_one_has_no_stock_or_balance_effects(): void
    {
        $foreignId = $this->address($this->otherCustomerId);
        $inactiveId = $this->address($this->customerId, 'Inactiva', 'Vieja 555', false);
        $orderId = $this->order();
        $this->assertNull(DB::table('business_orders')->where('id', $orderId)->value('delivery_destination'));
        $this->authorize($orderId);
        $batchId = $this->stock();

        foreach ([null, $foreignId, $inactiveId] as $addressId) {
            try {
                app(MoveStock::class)->dispatch($this->warehouse->id, (string) Str::uuid(), $this->line($orderId), $batchId, 1, false, $addressId);
                $this->fail('El despacho debe comprobar un destino válido del cliente.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 0]);
                $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 20]);
                $this->assertDatabaseCount('inventory_movements', 1);
                $this->assertDatabaseCount('inventory_audits', 1);
            }
        }

        $validId = $this->address($this->customerId);
        app(MoveStock::class)->dispatch($this->warehouse->id, (string) Str::uuid(), $this->line($orderId), $batchId, 1, false, $validId);
        $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 1]);
        $this->assertNull(DB::table('business_orders')->where('id', $orderId)->value('delivery_destination'));
    }

    public function test_dispatch_retry_preserves_its_explicit_destination_after_edit_and_inactivation(): void
    {
        $defaultId = $this->address($this->customerId);
        $alternateId = $this->address($this->customerId, 'Sucursal', 'Destino del despacho 456');
        $orderId = $this->order($defaultId);
        $this->authorize($orderId);
        $batchId = $this->stock();
        $token = (string) Str::uuid();
        $stock = app(MoveStock::class);
        $movementId = $stock->dispatch($this->warehouse->id, $token, $this->line($orderId), $batchId, 2, false, $alternateId);
        $payload = DB::table('inventory_audits')->orderByDesc('sequence')->value('payload');
        app(ManageAddresses::class)->save($this->admin->id, $this->customerId, $alternateId, 'Mudada', 'Otra 999', 'Posadas', 'Misiones', active: false);
        $this->assertSame($movementId, $stock->dispatch($this->warehouse->id, $token, $this->line($orderId), $batchId, 2, false, $alternateId));
        $this->assertSame($payload, DB::table('inventory_audits')->orderByDesc('sequence')->value('payload'));

        try {
            $stock->dispatch($this->warehouse->id, $token, $this->line($orderId), $batchId, 2, false, $defaultId);
            $this->fail('Reintentar con otra dirección no debe modificar una salida ya confirmada.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 2]);
            $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 18]);
            $this->assertDatabaseCount('inventory_movements', 2);
            $this->assertSame(2, app(SqlInventory::class)->verify()['sequence']);
        }
    }

    public function test_dispatch_http_input_cannot_replace_the_destination_with_another_customers_address(): void
    {
        $defaultId = $this->address($this->customerId);
        $alternateId = $this->address($this->customerId, 'Sucursal elegida', 'Sucursal 456');
        $foreignId = $this->address($this->otherCustomerId);
        $orderId = $this->order($defaultId);
        $this->authorize($orderId);
        $batchId = $this->stock();
        $request = ['order_line_id' => $this->line($orderId), 'batch_id' => $batchId, 'quantity' => 2];
        $this->actingAs($this->warehouse)->from(route('operations.index'))->post(route('operations.dispatch'), [
            ...$request, 'operation_id' => (string) Str::uuid(), 'delivery_address_id' => $foreignId,
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 0]);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 20]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->post(route('operations.dispatch'), [
            ...$request, 'operation_id' => (string) Str::uuid(), 'delivery_address_id' => $alternateId,
        ])->assertSessionHasNoErrors();
        $payload = json_decode(DB::table('inventory_audits')->orderByDesc('sequence')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($alternateId, $payload['delivery_destination']['id']);
        $this->assertSame('Sucursal 456', $payload['delivery_destination']['street']);
        $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 2]);
    }

    public function test_owner_withdrawal_can_be_recorded_without_inventing_a_delivery_address(): void
    {
        $orderId = $this->order();
        $this->authorize($orderId);
        $batchId = $this->stock();
        app(MoveStock::class)->dispatch($this->warehouse->id, (string) Str::uuid(), $this->line($orderId), $batchId, 1, true);
        $this->assertDatabaseHas('inventory_movements', ['kind' => 'owner_withdrawal', 'quantity' => 1]);
        $this->assertDatabaseCount('customer_delivery_addresses', 0);
        $this->assertSame(2, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_audit_failure_rolls_back_a_dispatch_with_an_explicit_destination(): void
    {
        $addressId = $this->address($this->customerId);
        $orderId = $this->order($addressId);
        $snapshot = $this->destination($orderId);
        $this->authorize($orderId);
        $batchId = $this->stock();
        config(['inventory.audit_key' => null]);

        try {
            app(MoveStock::class)->dispatch($this->warehouse->id, (string) Str::uuid(), $this->line($orderId), $batchId, 3, false, $addressId);
            $this->fail('Sin auditoría no puede quedar confirmado un despacho.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseHas('business_order_lines', ['order_id' => $orderId, 'dispatched' => 0]);
            $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 20]);
            $this->assertDatabaseCount('inventory_movements', 1);
            $this->assertSame($snapshot, $this->destination($orderId));
        }
    }

    public function test_additive_migrations_preserve_previous_orders_and_signed_movements_without_inventing_destinations(): void
    {
        $addressMigration = require base_path('src/ClientesAdministracion/Infrastructure/Database/Migrations/2026_11_04_220000_create_customer_addresses.php');
        $orderMigration = require base_path('src/VentasPedidos/Infrastructure/Database/Migrations/2026_11_04_220100_add_order_delivery_destination.php');
        $orderMigration->down();
        $addressMigration->down();
        $registrationId = (string) Str::uuid();
        $lines = [['variant_id' => $this->variantId, 'quantity' => 10]];
        $digest = hash('sha256', json_encode([$this->customerId, $this->seller->id, 'S', $lines], JSON_THROW_ON_ERROR));
        $orderId = DB::table('business_orders')->insertGetId([
            'registration_id' => $registrationId, 'request_digest' => $digest, 'number' => 'NP-LEGACY-001',
            'customer_id' => $this->customerId, 'seller_id' => $this->seller->id, 'origin' => 'S', 'status' => 'Confirmado', 'created_at' => '2026-01-01 08:00:00',
        ]);
        DB::table('business_order_lines')->insert(['order_id' => $orderId, ...$lines[0], 'dispatched' => 0]);
        $this->stock();
        $audit = DB::table('inventory_audits')->first();
        $addressMigration->up();
        $orderMigration->up();
        $this->assertDatabaseHas('business_orders', [
            'id' => $orderId, 'registration_id' => $registrationId, 'request_digest' => $digest,
            'delivery_address_id' => null, 'delivery_destination' => null, 'created_at' => '2026-01-01 08:00:00',
        ]);
        $this->assertDatabaseCount('customer_delivery_addresses', 0);
        $this->assertDatabaseCount('customer_credit_entries', 2);
        $this->assertSame($orderId, $this->order(null, $registrationId));
        $after = DB::table('inventory_audits')->first();
        $this->assertSame($audit->payload, $after->payload);
        $this->assertSame($audit->digest, $after->digest);
        $this->assertSame(1, app(SqlInventory::class)->verify()['sequence']);
    }

    public function test_legacy_signed_dispatch_without_a_destination_can_be_verified_and_replayed_without_changing_business_data(): void
    {
        $orderId = $this->order();
        $lineId = $this->line($orderId);
        $this->authorize($orderId);
        DB::table('business_orders')->where('id', $orderId)->update(['status' => 'Parcial']);
        DB::table('business_order_lines')->where('id', $lineId)->update(['dispatched' => 4]);
        $batchId = DB::table('inventory_batches')->insertGetId([
            'variant_id' => $this->variantId, 'lot' => 'LOTE-HISTORICO', 'manufactured_on' => '2026-01-01', 'quantity' => 6,
        ]);
        $token = (string) Str::uuid();
        $request = ['kind' => 'dispatch', 'order_line_id' => $lineId, 'batch_id' => $batchId, 'quantity' => 4, 'owner_withdrawal' => false];
        ksort($request);
        $requestDigest = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
        $occurredAt = '2026-01-02T08:00:00.000000Z';
        $movementId = DB::table('inventory_movements')->insertGetId([
            'operation_id' => $token, 'request_digest' => $requestDigest, 'actor_id' => $this->warehouse->id,
            'kind' => 'dispatch', 'quantity' => 4, 'batch_id' => $batchId, 'order_line_id' => $lineId, 'occurred_at' => $occurredAt,
        ]);
        $payload = json_encode([
            'sequence' => 1, 'movement_id' => $movementId, 'operation_id' => $token, 'request_digest' => $requestDigest,
            'actor_id' => $this->warehouse->id, 'actor_name' => $this->warehouse->name, 'kind' => 'dispatch', 'quantity' => 4,
            'batch_id' => $batchId, 'batch' => (array) DB::table('inventory_batches')->where('id', $batchId)->first(),
            'order_line_id' => $lineId, 'occurred_at' => $occurredAt,
            'authorization' => (array) DB::table('dispatch_authorizations')->where('order_id', $orderId)->first(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $previousDigest = str_repeat('0', 64);
        $digest = hash_hmac('sha256', $previousDigest."\n".$payload, config('inventory.audit_key'));
        DB::table('inventory_audits')->insert([
            'sequence' => 1, 'movement_id' => $movementId, 'payload' => $payload, 'previous_digest' => $previousDigest, 'digest' => $digest,
        ]);
        DB::table('inventory_audit_head')->where('id', 1)->update(['sequence' => 1, 'digest' => $digest]);
        $this->assertArrayNotHasKey('delivery_destination', json_decode($payload, true, flags: JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('delivery_address_id', $request);
        $this->assertSame(['sequence' => 1, 'digest' => $digest], app(SqlInventory::class)->verify());
        $this->assertSame($movementId, app(MoveStock::class)->dispatch($this->warehouse->id, $token, $lineId, $batchId, 4, false, null));
        $this->assertDatabaseHas('business_order_lines', ['id' => $lineId, 'quantity' => 10, 'dispatched' => 4]);
        $this->assertDatabaseHas('business_orders', ['id' => $orderId, 'status' => 'Parcial', 'delivery_destination' => null]);
        $this->assertDatabaseHas('inventory_batches', ['id' => $batchId, 'quantity' => 6]);
        $this->assertDatabaseCount('customer_delivery_addresses', 0);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('inventory_audits', 1);
        $this->assertSame($payload, DB::table('inventory_audits')->value('payload'));
        $this->assertSame($digest, DB::table('inventory_audits')->value('digest'));
        $this->assertSame(['sequence' => 1, 'digest' => $digest], app(SqlInventory::class)->verify());
    }

    public function test_address_content_is_escaped_in_profiles_and_edit_forms(): void
    {
        $street = '<script>alert("domicilio")</script>';
        $addressId = $this->address($this->customerId, '<b>Central</b>', $street);
        $this->actingAs($this->admin)->get(route('customers.show', $this->customerId))->assertOk()
            ->assertDontSee($street, false)->assertDontSee('<b>Central</b>', false)->assertSee('&lt;script&gt;', false);
        $this->get(route('customers.addresses.edit', [$this->customerId, $addressId]))->assertOk()->assertDontSee($street, false)->assertSee('&lt;script&gt;', false);
    }
}
