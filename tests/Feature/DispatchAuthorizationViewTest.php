<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\UI\DispatchAuthorizations;
use Simon\Shared\UI\ManagementActions;
use Simon\VentasPedidos\Application\RegisterOrder;
use Tests\TestCase;

class DispatchAuthorizationViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $first;

    private int $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $customer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente Uno', '20123456789', '');
        $otherCustomer = app(ManageCustomers::class)->register($this->admin->id, 'Cliente Dos', '20987654321', '');
        $variant = app(PrepareInventory::class)->variant($this->admin->id, 'BOT-01', 'Botín', 'Negro', '40');
        $orders = app(RegisterOrder::class);
        $this->first = $orders->handle($this->admin->id, (string) Str::uuid(), $customer, 'F', [['variant_id' => $variant, 'quantity' => 3]]);
        $this->second = $orders->handle($this->admin->id, (string) Str::uuid(), $otherCustomer, 'F', [['variant_id' => $variant, 'quantity' => 5]]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_each_order_shows_its_own_status_details_and_saved_validation(): void
    {
        app(ManageCustomers::class)->authorize($this->admin->id, $this->first, true, false, 'Entregar por la mañana');
        $this->get(route('operations.authorization'))->assertOk()->assertSee('Cliente Uno')->assertSee('Cliente Dos')
            ->assertSee('Facturación')->assertSee('Crédito')->assertDontSee('name="order_id"', false);

        $component = Livewire::test(DispatchAuthorizations::class)->call('selectOrder', $this->first)
            ->assertSee('Validación del pedido NP-000001')->assertSee('Cliente Uno')->assertSee('BOT-01')
            ->assertSee('Entregar por la mañana')->assertSee('Última validación:');
        $component->call('selectOrder', $this->second)->assertSee('Validación del pedido NP-000002')
            ->assertSee('Todavía no se registró una validación')->assertDontSee('Entregar por la mañana');

        $response = $this->get(route('operations.authorization', ['pedido' => $this->first]))->assertOk()->assertSee('Entregar por la mañana');
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//input[@name="billing" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="credit" and @checked]')->length);
    }

    public function test_livewire_saves_only_the_selected_order_and_restores_its_fields(): void
    {
        app(ManageCustomers::class)->authorize($this->admin->id, $this->first, true, false, 'Condiciones originales');
        Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/authorize', 'POST', [
            'order_id' => $this->second, 'billing' => '1', 'credit' => '1', 'conditions' => 'Retirar en depósito',
        ])->assertRedirect(route('operations.authorization', ['pedido' => $this->second]));
        $this->assertDatabaseHas('dispatch_authorizations', ['order_id' => $this->first, 'billing_validated' => true, 'credit_validated' => false, 'conditions' => 'Condiciones originales']);
        $this->assertDatabaseHas('dispatch_authorizations', ['order_id' => $this->second, 'billing_validated' => true, 'credit_validated' => true, 'conditions' => 'Retirar en depósito']);
        $this->get(route('operations.authorization', ['pedido' => $this->second]))->assertSee('Retirar en depósito');
    }

    public function test_search_and_pagination_cover_orders_beyond_the_previous_recent_limit(): void
    {
        $template = (array) DB::table('business_orders')->where('id', $this->first)->first();
        unset($template['id']);
        for ($number = 0; $number < 205; $number++) {
            DB::table('business_orders')->insert([...$template, 'registration_id' => (string) Str::uuid(), 'number' => 'EXTRA-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)]);
        }
        Livewire::test(DispatchAuthorizations::class)->assertSee('207 pedidos encontrados')->assertSee('Siguiente')
            ->set('query', 'NP-000001')->call('search')->assertSee('1 pedido encontrado')->assertSee('Cliente Uno')->assertDontSee('Cliente Dos');
        Livewire::test(DispatchAuthorizations::class)->set('query', 'Cliente Dos')->call('search')->assertSee('1 pedido encontrado')->assertSee('NP-000002')->assertDontSee('NP-000001');
        Livewire::test(DispatchAuthorizations::class)->set('query', '%')->call('search')->assertSee('0 pedidos encontrados');
        $this->get(route('operations.authorization', ['pedido' => $this->first]))->assertOk()->assertSee('Validación del pedido NP-000001');
    }

    public function test_permissions_are_rechecked_and_invalid_order_states_are_rejected(): void
    {
        $role = app(ManageAccess::class)->saveRole($this->admin->id, null, 'Validador', ['dispatch.authorize']);
        $validator = app(ManageAccess::class)->saveUser($this->admin->id, null, 'Validador', 'validator@example.test', 'long-test-password', null, true, [$role]);
        $this->actingAs(User::findOrFail($validator));
        $component = Livewire::test(DispatchAuthorizations::class)->assertSee('Cliente Uno');
        DB::table('access_user_roles')->where('user_id', $validator)->delete();
        $component->call('selectOrder', $this->first)->assertForbidden();
        Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/authorize', 'POST', ['order_id' => $this->first, 'billing' => '1', 'credit' => '1', 'conditions' => 'Prueba'])->assertForbidden();
        $this->assertDatabaseMissing('dispatch_authorizations', ['order_id' => $this->first]);

        $this->actingAs($this->admin);
        DB::table('business_orders')->where('id', $this->first)->update(['status' => 'Anulado']);
        Livewire::test(DispatchAuthorizations::class)->call('selectOrder', $this->first)->assertSee('Pedido no disponible');
        $invalid = Livewire::test(ManagementActions::class)->call('submit', '/admin/operaciones/authorize', 'POST', ['order_id' => $this->first, 'billing' => '1', 'credit' => '1', 'conditions' => 'Prueba']);
        $this->assertNotEmpty($invalid->get('messages')['operation']);
        $this->assertDatabaseMissing('dispatch_authorizations', ['order_id' => $this->first]);
    }
}
