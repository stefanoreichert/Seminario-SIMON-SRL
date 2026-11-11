<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\ListOrders;
use Simon\VentasPedidos\Application\RegisterOrder;
use Simon\VentasPedidos\UI\OrderDestinationSelector;
use Simon\VentasPedidos\UI\OrderLinesEditor;
use Tests\TestCase;

class MultipleArticleOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $customerId;

    private int $addressId;

    /** @var list<int> */
    private array $variants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->customerId = app(ManageCustomers::class)->register($this->admin->id, 'Cliente multilínea', '20-12345678-9', 'Control manual');
        $this->addressId = app(ManageAddresses::class)->save($this->admin->id, $this->customerId, null, 'Sucursal central', 'Calle 123', 'Posadas', 'Misiones');
        $catalog = app(PrepareInventory::class);
        $this->variants = [
            $catalog->variant($this->admin->id, 'MULTI-01', 'Botín', 'Negro', '40'),
            $catalog->variant($this->admin->id, 'MULTI-02', 'Zapato', 'Marrón', '41'),
        ];
    }

    /** @return array{operation_id:string,customer_id:int,delivery_address_id:int,origin:string,lines:list<array{variant_id:int,quantity:int}>} */
    private function data(): array
    {
        return [
            'operation_id' => (string) Str::uuid(), 'customer_id' => $this->customerId, 'delivery_address_id' => $this->addressId, 'origin' => 'F',
            'lines' => [['variant_id' => $this->variants[0], 'quantity' => 5], ['variant_id' => $this->variants[1], 'quantity' => 8]],
        ];
    }

    /** @param array<string, mixed> $data */
    private function register(array $data): int
    {
        return app(RegisterOrder::class)->handle($this->admin->id, $data['operation_id'], $data['customer_id'], $data['origin'], $data['lines'], $data['delivery_address_id']);
    }

    public function test_multiple_articles_are_confirmed_together_with_customer_origin_and_destination(): void
    {
        $data = $this->data();
        $this->actingAs($this->admin)->from(route('operations.orders'))->post(route('operations.order'), $data)
            ->assertRedirect(route('operations.orders', ['origin' => 'F']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 2);
        $order = DB::table('business_orders')->first();
        $this->assertSame($this->customerId, $order->customer_id);
        $this->assertSame('F', $order->origin);
        $this->assertSame('Confirmado', $order->status);
        $this->assertSame($this->addressId, $order->delivery_address_id);
        $destination = json_decode($order->delivery_destination, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Calle 123', $destination['street']);
        $result = app(ListOrders::class)->handle($this->admin->id, 'F');
        $this->assertSame($data['lines'], array_map(fn (object $line): array => ['variant_id' => $line->variant_id, 'quantity' => $line->quantity], $result['lines']));
        $this->get(route('operations.orders', ['origin' => 'F']))->assertOk()->assertSee('MULTI-01')->assertSee('MULTI-02')->assertSee('Calle 123')->assertSee('F · Fábrica');
    }

    public function test_invalid_http_lines_never_store_a_partial_order(): void
    {
        $this->actingAs($this->admin)->from(route('operations.orders'));

        foreach ([
            [['variant_id' => '', 'quantity' => 2], 'lines.1.variant_id'],
            [['variant_id' => $this->variants[1], 'quantity' => 0], 'lines.1.quantity'],
            [['variant_id' => $this->variants[1], 'quantity' => -1], 'lines.1.quantity'],
            [['variant_id' => $this->variants[1], 'quantity' => '2.5'], 'lines.1.quantity'],
            [['variant_id' => $this->variants[1], 'quantity' => true], 'lines.1.quantity'],
            [['variant_id' => $this->variants[1], 'quantity' => 1000001], 'lines.1.quantity'],
            [['variant_id' => 999999, 'quantity' => 8], 'lines.1.variant_id'],
        ] as [$invalid, $field]) {
            $data = $this->data();
            $data['lines'][1] = $invalid;
            $this->post(route('operations.order'), $data)->assertSessionHasErrors($field);
            $this->assertDatabaseCount('business_orders', 0);
            $this->assertDatabaseCount('business_order_lines', 0);
        }
    }

    public function test_http_retry_keeps_the_confirmed_destination_even_after_its_address_is_inactivated(): void
    {
        $data = $this->data();
        $this->actingAs($this->admin)->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $before = DB::table('business_orders')->first();
        app(ManageAddresses::class)->save($this->admin->id, $this->customerId, $this->addressId, 'Dirección mudada', 'Nueva 999', 'Posadas', 'Misiones', active: false);

        $this->post(route('operations.order'), $data)
            ->assertRedirect(route('operations.orders', ['origin' => 'F']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 2);
        $after = DB::table('business_orders')->first();
        $this->assertSame($before->id, $after->id);
        $this->assertSame($before->number, $after->number);
        $this->assertSame($before->delivery_destination, $after->delivery_destination);

        $this->post(route('operations.order'), [...$data, 'operation_id' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 2);
    }

    public function test_validation_keeps_all_rows_and_header_values_until_the_user_corrects_them(): void
    {
        $data = $this->data();
        $data['lines'][1]['quantity'] = 0;
        $this->actingAs($this->admin)->from(route('operations.orders'))->post(route('operations.order'), $data)
            ->assertRedirect(route('operations.orders'));
        $response = $this->get(route('operations.orders'))->assertOk();
        $response->assertSee('La cantidad de la línea 2 debe ser mayor que cero.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//*[@data-order-lines]/*[@data-order-line]')->length);
        $this->assertSame($data['operation_id'], $xpath->query('//form[@data-order-form]//input[@name="operation_id"]')->item(0)->getAttribute('value'));
        $this->assertSame((string) $this->customerId, $xpath->query('//form[@data-order-form]//select[@name="customer_id"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame((string) $this->addressId, $xpath->query('//form[@data-order-form]//select[@name="delivery_address_id"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame('true', $xpath->query('//form[@data-order-form]//select[@name="delivery_address_id"]')->item(0)->getAttribute('data-addresses-ready'));
        $this->assertSame('F', $xpath->query('//form[@data-order-form]//select[@name="origin"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame('5', $xpath->query('//input[@name="lines[0][quantity]"]')->item(0)->getAttribute('value'));
        $this->assertSame('0', $xpath->query('//input[@name="lines[1][quantity]"]')->item(0)->getAttribute('value'));
        $this->assertSame((string) $this->variants[1], $xpath->query('//select[@name="lines[1][variant_id]"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//*[@id="order-line-1-quantity-error"]')->length);
        $data['lines'][1]['quantity'] = '8';
        $this->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 2);
        $this->assertDatabaseHas('business_order_lines', ['variant_id' => $this->variants[1], 'quantity' => 8]);
    }

    public function test_repeated_articles_are_preserved_for_correction_and_never_silently_combined(): void
    {
        $data = $this->data();
        $data['lines'][1]['variant_id'] = $this->variants[0];
        $this->actingAs($this->admin)->from(route('operations.orders'))->post(route('operations.order'), $data)
            ->assertRedirect(route('operations.orders'));
        $this->assertDatabaseCount('business_orders', 0);
        $response = $this->get(route('operations.orders'))->assertOk()
            ->assertSee('El artículo de la línea 1 está repetido.')
            ->assertSee('El artículo de la línea 2 está repetido.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//*[@data-order-lines]/*[@data-order-line]')->length);
        $this->assertSame('5', $xpath->query('//input[@name="lines[0][quantity]"]')->item(0)->getAttribute('value'));
        $this->assertSame('8', $xpath->query('//input[@name="lines[1][quantity]"]')->item(0)->getAttribute('value'));
        $data['lines'] = [['variant_id' => $this->variants[0], 'quantity' => 13]];
        $this->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_order_lines', 1);
        $this->assertDatabaseHas('business_order_lines', ['variant_id' => $this->variants[0], 'quantity' => 13]);
    }

    public function test_direct_calls_reject_malformed_rows_duplicates_and_invalid_quantities(): void
    {
        foreach ([
            [], ['not-an-array'], [['quantity' => 2]], [['variant_id' => $this->variants[0]]],
            [['variant_id' => $this->variants[0], 'quantity' => 0]],
            [['variant_id' => $this->variants[0], 'quantity' => 2.5]],
            [['variant_id' => $this->variants[0], 'quantity' => true]],
            [['variant_id' => $this->variants[0], 'quantity' => '2']],
            [['variant_id' => $this->variants[0], 'quantity' => 1000001]],
            [['variant_id' => 999999, 'quantity' => 2]],
            [['variant_id' => $this->variants[0], 'quantity' => 1], ['variant_id' => $this->variants[0], 'quantity' => 2]],
        ] as $lines) {
            try {
                $this->register([...$this->data(), 'lines' => $lines]);
                $this->fail('La nota completa debe rechazarse también fuera de HTTP.');
            } catch (BusinessRuleViolation $exception) {
                $this->assertNotEmpty($exception->getMessage());
                $this->assertDatabaseCount('business_orders', 0);
                $this->assertDatabaseCount('business_order_lines', 0);
            }
        }
    }

    public function test_client_input_cannot_attach_lines_to_another_order_or_set_dispatched_quantities(): void
    {
        $existingId = $this->register($this->data());
        $data = $this->data();
        $data['lines'][1]['order_id'] = $existingId;
        $data['lines'][1]['dispatched'] = 8;
        $this->actingAs($this->admin)->from(route('operations.orders'))->post(route('operations.order'), $data)->assertSessionHasErrors('lines.1');

        try {
            $this->register($data);
            $this->fail('Las columnas de persistencia no forman parte de una línea de entrada.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseCount('business_orders', 1);
            $this->assertDatabaseCount('business_order_lines', 2);
            $this->assertSame(0, DB::table('business_order_lines')->sum('dispatched'));
        }
    }

    public function test_a_failure_on_a_later_line_rolls_back_the_header_and_all_preceding_lines(): void
    {
        $variantId = $this->variants[1];
        DB::unprepared("CREATE TRIGGER reject_test_order_line BEFORE INSERT ON business_order_lines WHEN NEW.variant_id = {$variantId} BEGIN SELECT RAISE(ABORT, 'Fallo de línea simulado'); END");
        $data = $this->data();

        try {
            $this->register($data);
            $this->fail('El fallo de la segunda línea debe revertir el pedido completo.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseCount('business_orders', 0);
            $this->assertDatabaseCount('business_order_lines', 0);
            $this->assertDatabaseCount('customer_credit_entries', 1);
        }

        DB::unprepared('DROP TRIGGER reject_test_order_line');
        $id = $this->register($data);
        $this->assertSame($id, $this->register($data));
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 2);
    }

    public function test_missing_customer_or_foreign_destination_cannot_leave_a_partial_order(): void
    {
        $otherCustomer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente ajeno', '30-87654321-0', '');
        $otherAddress = app(ManageAddresses::class)->save($this->admin->id, $otherCustomer, null, 'Ajena', 'Otra 456', 'Posadas', 'Misiones');

        foreach ([['customer_id' => 999999], ['delivery_address_id' => $otherAddress]] as $change) {
            try {
                $this->register([...$this->data(), ...$change]);
                $this->fail('El encabezado inválido no permite guardar líneas.');
            } catch (BusinessRuleViolation) {
                $this->assertDatabaseCount('business_orders', 0);
                $this->assertDatabaseCount('business_order_lines', 0);
            }
        }
    }

    public function test_one_hundred_distinct_articles_are_allowed_and_a_larger_note_is_rejected(): void
    {
        $lines = array_map(fn (int $id): array => ['variant_id' => $id, 'quantity' => 1], $this->variants);

        for ($index = 2; $index < 100; $index++) {
            $id = app(PrepareInventory::class)->variant($this->admin->id, 'BOUND-'.$index, 'Modelo '.$index, 'Negro', '40');
            $lines[] = ['variant_id' => $id, 'quantity' => 1];
        }

        $data = [...$this->data(), 'lines' => $lines];
        $this->actingAs($this->admin)->from(route('operations.orders'))->post(route('operations.order'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 100);
        $tooMany = [...$lines, ['variant_id' => $this->variants[0], 'quantity' => 1]];
        $this->post(route('operations.order'), [...$this->data(), 'lines' => $tooMany])->assertSessionHasErrors('lines');

        try {
            $this->register([...$this->data(), 'lines' => $tooMany]);
            $this->fail('El límite de líneas también pertenece al caso de uso.');
        } catch (BusinessRuleViolation) {
            $this->assertDatabaseCount('business_orders', 1);
            $this->assertDatabaseCount('business_order_lines', 100);
        }
    }

    public function test_new_order_form_starts_with_one_blank_row_and_uses_filament_controls(): void
    {
        $response = $this->actingAs($this->admin)->get(route('operations.orders'))->assertOk()
            ->assertSee('fi-main-sidebar', false)->assertSee('wire:click="addLine"', false)->assertSee('data-remove-line', false)
            ->assertDontSee('<x-simon::', false)->assertDontSee('<x-filament::', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@data-order-lines]/*[@data-order-line]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-order-lines]//button[@data-remove-line and @disabled]')->length);
        $this->assertSame('100', $xpath->query('//form[@data-order-form]')->item(0)->getAttribute('data-max-lines'));
        $this->assertSame('', $xpath->query('//*[@data-order-lines]//input[@name="lines[0][quantity]"]')->item(0)->getAttribute('value'));
    }

    public function test_livewire_order_lines_preserve_values_when_adding_and_removing_rows(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::test(OrderLinesEditor::class, ['lines' => [['variant_id' => '', 'quantity' => '']]])
            ->set('lines.0.variant_id', (string) $this->variants[0])
            ->set('lines.0.quantity', '5')
            ->call('addLine')
            ->assertSet('lines.0.quantity', '5')
            ->set('lines.1.variant_id', (string) $this->variants[1])
            ->set('lines.1.quantity', '8')
            ->call('removeLine', 0)
            ->assertSet('lines.0.variant_id', (string) $this->variants[1])
            ->assertSet('lines.0.quantity', '8');
        $component->call('removeLine', 0)->assertCount('lines', 1);
    }

    public function test_livewire_order_lines_recheck_permissions_on_update(): void
    {
        $user = User::factory()->create();
        $role = DB::table('access_roles')->insertGetId(['name' => 'Pedidos temporales']);
        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => 'orders.create']);
        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::test(OrderLinesEditor::class, ['lines' => [['variant_id' => '', 'quantity' => '']]]);
        DB::table('access_role_permissions')->where('role_id', $role)->delete();
        $component->call('addLine')->assertForbidden();
    }

    public function test_livewire_order_destination_changes_addresses_with_the_customer(): void
    {
        $other = app(ManageCustomers::class)->register($this->admin->id, 'Otro cliente', '20345678901', '');
        $otherAddress = app(ManageAddresses::class)->save($this->admin->id, $other, null, 'Otro destino', 'Otra calle', 'Posadas', 'Misiones');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(OrderDestinationSelector::class, ['customer' => (string) $this->customerId, 'address' => (string) $this->addressId])
            ->assertSee('Sucursal central')
            ->set('customer', (string) $other)
            ->assertSet('address', '')
            ->assertSee('Otro destino')
            ->assertDontSee('Sucursal central')
            ->set('address', (string) $otherAddress)
            ->set('customer', (string) $this->customerId)
            ->assertSet('address', '')
            ->assertSee('Sucursal central')
            ->assertDontSee('Otro destino');
    }

    public function test_an_order_does_not_modify_inventory_or_require_an_inventory_audit_write(): void
    {
        config(['inventory.audit_key' => str_repeat('test-order-audit-key-', 3)]);
        app(MoveStock::class)->opening($this->admin->id, (string) Str::uuid(), $this->variants[0], 'LOTE-EXISTENTE', '2026-01-01', 10);
        app(PrepareInventory::class)->production($this->admin->id, (string) Str::uuid(), $this->variants[0], 'LOTE-EN-PROCESO', '2026-01-01', 4);
        $audit = DB::table('inventory_audits')->first();
        config(['inventory.audit_key' => null]);
        $this->register($this->data());
        $this->assertDatabaseHas('inventory_batches', ['quantity' => 10]);
        $this->assertDatabaseHas('production_batches', ['in_process' => 4]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('inventory_audits', 1);
        $this->assertSame($audit->payload, DB::table('inventory_audits')->first()->payload);
        $this->assertSame($audit->digest, DB::table('inventory_audits')->first()->digest);
        $this->assertDatabaseCount('business_order_lines', 2);
    }

    public function test_unauthorized_users_cannot_submit_multiple_articles_even_with_forged_actor_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('operations.order'), [...$this->data(), 'actor_id' => $this->admin->id])->assertForbidden();

        try {
            app(RegisterOrder::class)->handle($user->id, (string) Str::uuid(), $this->customerId, 'F', $this->data()['lines'], $this->addressId);
            $this->fail('El caso de uso comprueba el permiso fuera de HTTP.');
        } catch (PermissionDenied) {
            $this->assertDatabaseCount('business_orders', 0);
            $this->assertDatabaseCount('business_order_lines', 0);
        }
    }

    public function test_malformed_old_input_is_safe_to_display_without_dropping_valid_rows(): void
    {
        $data = $this->data();
        $data['lines'][1]['variant_id'] = '<img src=x onerror=alert(1)>';
        $this->actingAs($this->admin)->from(route('operations.orders'))->post(route('operations.order'), $data)->assertSessionHasErrors('lines.1.variant_id');
        $response = $this->get(route('operations.orders'))->assertOk()->assertDontSee('<img src=x onerror=alert(1)>', false)->assertSee('&lt;img', false);
        $this->assertSame(2, $this->xpath($response->getContent())->query('//*[@data-order-lines]/*[@data-order-line]')->length);
        $this->assertDatabaseCount('business_orders', 0);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }
}
