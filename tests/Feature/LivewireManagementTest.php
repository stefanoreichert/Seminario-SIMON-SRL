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
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\Shared\UI\LiveBalances;
use Simon\Shared\UI\ManagementActions;
use Tests\TestCase;

class LivewireManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_livewire_forms_preserve_controller_validation_and_save_through_the_existing_use_case(): void
    {
        $component = Livewire::test(ManagementActions::class)
            ->call('submit', '/admin/clientes', 'POST', ['name' => '', 'cuit' => '']);
        $this->assertNotEmpty($component->get('messages')['name']);
        $this->assertDatabaseCount('business_customers', 0);
        $component->call('submit', '/admin/clientes', 'POST', ['name' => ' Comercio Livewire ', 'cuit' => '20123456789', 'email' => '', 'phone' => ''])
            ->assertSet('messages', []);
        $this->assertDatabaseHas('business_customers', ['name' => 'Comercio Livewire', 'email' => null, 'phone' => null]);
    }

    public function test_livewire_order_submissions_use_form_request_and_keep_retry_protection(): void
    {
        $customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente', '20123456789', '');
        $variant = app(PrepareInventory::class)->variant($this->admin->id, 'LW-01', 'Botín', 'Negro', '40');
        $fields = ['operation_id' => (string) Str::uuid(), 'customer_id' => (string) $customer, 'origin' => 'F', 'delivery_address_id' => '', 'lines' => [['variant_id' => (string) $variant, 'quantity' => '3']]];
        $component = Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/order', 'POST', $fields);
        $this->assertSame([], $component->get('messages'), json_encode($component->get('messages')));
        $component->assertRedirect();
        Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/order', 'POST', $fields)->assertRedirect();
        $this->assertDatabaseCount('business_orders', 1);
        $this->assertDatabaseCount('business_order_lines', 1);
        $invalid = Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/order', 'POST', [...$fields, 'lines' => [['variant_id' => $variant, 'quantity' => '2.5']]]);
        $this->assertNotEmpty($invalid->get('messages')['lines.0.quantity']);
        $this->assertDatabaseCount('business_orders', 1);
    }

    public function test_livewire_dispatcher_checks_target_permissions_and_rejects_unlisted_routes(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ManagementActions::class)->call('submit', '/admin/clientes', 'POST', ['actor_id' => $this->admin->id])->assertForbidden();
        Livewire::test(ManagementActions::class)->call('addresses', 1)->assertForbidden();
        $this->actingAs($this->admin);
        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios', 'DELETE', [])->assertStatus(422);
        Livewire::test(ManagementActions::class)->call('submit', '//example.test/admin/clientes', 'POST', [])->assertStatus(422);
        Livewire::test(ManagementActions::class)->call('submit', '/gestion/clientes', 'POST', [])->assertStatus(422);
    }

    public function test_livewire_actions_bind_customer_address_and_order_route_parameters(): void
    {
        $customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente', '20123456789', '');
        $address = app(ManageAddresses::class)->save($this->admin->id, $customer, null, 'Local', 'Calle inicial', 'Posadas', 'Misiones');
        Livewire::test(ManagementActions::class)->call('submit', "/admin/clientes/{$customer}/direcciones/{$address}", 'PUT', ['label' => 'Local', 'street' => 'Calle actualizada', 'locality' => 'Posadas', 'province' => 'Misiones', 'active' => '0'])->assertRedirect();
        $this->assertDatabaseHas('customer_delivery_addresses', ['id' => $address, 'street' => 'Calle actualizada', 'is_active' => false]);
        $variant = app(PrepareInventory::class)->variant($this->admin->id, 'LW-ESTADO', 'Botín', 'Negro', '40');
        Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/order', 'POST', ['operation_id' => (string) Str::uuid(), 'customer_id' => $customer, 'origin' => 'F', 'intent' => 'draft', 'lines' => [['variant_id' => $variant, 'quantity' => '5']]])->assertRedirect();
        $order = DB::table('business_orders')->value('id');
        Livewire::test(ManagementActions::class)->call('submit', "/admin/pedidos/{$order}/estado", 'POST', ['operation_id' => (string) Str::uuid(), 'revision' => '0', 'action' => 'confirm'])->assertRedirect();
        $this->assertDatabaseHas('business_orders', ['id' => $order, 'status' => 'Confirmado']);
        Livewire::test(ManagementActions::class)->call('submit', "/admin/pedidos/{$order}/estado", 'POST', ['operation_id' => (string) Str::uuid(), 'revision' => '1', 'action' => 'cancel', 'reason' => 'Anulación sintética'])->assertRedirect();
        $this->assertDatabaseHas('business_orders', ['id' => $order, 'status' => 'Anulado']);
    }

    public function test_livewire_balances_recheck_permissions_on_every_refresh(): void
    {
        $component = Livewire::test(LiveBalances::class, ['kind' => 'operations', 'parameters' => ['section' => 'stock']])->assertSee('Stock físico');
        DB::table('access_user_roles')->where('user_id', $this->admin->id)->delete();
        $component->call('$refresh')->assertForbidden();
    }
}
