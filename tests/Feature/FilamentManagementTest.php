<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Tests\TestCase;

class FilamentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
    }

    public function test_all_management_screens_share_the_native_panel_and_render_their_controls(): void
    {
        $customerId = app(ManageCustomers::class)->register($this->admin->id, 'Cliente del panel', '20-12345678-9', 'Observación privada');
        $addressId = app(ManageAddresses::class)->save($this->admin->id, $customerId, null, 'Central', 'Calle 123', 'Posadas', 'Misiones');
        $this->actingAs($this->admin);

        foreach ([
            route('filament.admin.pages.dashboard'), route('access.index'), route('access.roles'),
            route('customers.index'), route('customers.create'), route('customers.show', $customerId),
            route('customers.addresses.create', $customerId), route('customers.addresses.edit', [$customerId, $addressId]),
            route('operations.index'), route('operations.orders'), route('operations.catalog'), route('operations.production.index'),
            route('operations.stock'), route('operations.dispatch.index'), route('operations.authorization'), route('operations.audit'),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('fi-main-sidebar', false)->assertSee('fi-panel-admin', false)
                ->assertSee('Comercial')->assertSee('Fábrica')->assertSee('Administración')
                ->assertDontSee('<x-simon::', false)->assertDontSee('<x-filament::', false)->assertDontSee('operations-header', false);
        }
    }

    public function test_native_checkbox_values_preserve_existing_user_and_role_assignments(): void
    {
        $response = $this->actingAs($this->admin)->get(route('access.index', ['editar' => $this->admin->id]))->assertOk()->assertSee('fi-checkbox-input', false);
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $this->assertGreaterThanOrEqual(1, $xpath->query('//input[@name="active" and @type="checkbox" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="role_ids[]" and @checked]')->length);
        $this->get(route('access.roles', ['nuevo' => 1]))->assertOk()->assertSee('fi-checkbox-input', false)->assertDontSee('<x-simon::', false);
    }

    public function test_modules_are_separated_and_balance_refresh_keeps_the_requested_scope(): void
    {
        $this->actingAs($this->admin)->get(route('access.index'))->assertOk()->assertSee('Crear usuario')->assertDontSee('Crear rol');
        $this->get(route('access.roles'))->assertOk()->assertSee('Crear rol')->assertDontSee('Crear usuario');
        $this->get(route('operations.orders'))->assertOk()->assertSee('Registrar nota de pedido')->assertDontSee('Confirmar despacho o retiro');
        $this->get(route('operations.stock'))->assertOk()->assertSee('Dar de alta productos terminados')->assertDontSee('Registrar nota de pedido');
        $this->get(route('operations.dispatch.index'))->assertOk()->assertSee('Confirmar despacho o retiro')->assertDontSee('Dar de alta productos terminados');
        $this->get(route('operations.snapshot', ['section' => 'production']))->assertOk()->assertSee('Producción en proceso')->assertDontSee('Mercadería pendiente')->assertDontSee('<form', false);
        $this->get(route('operations.snapshot', ['section' => 'invalid']))->assertNotFound();
    }

    public function test_sidebar_visibility_and_direct_module_permissions_match_for_a_seller(): void
    {
        $access = app(ManageAccess::class);
        $roleId = $access->saveRole($this->admin->id, null, 'Vendedor', ['orders.create']);
        $sellerId = $access->saveUser($this->admin->id, null, 'Vendedor de prueba', 'seller@example.test', 'test-seller-password', 'S', true, [$roleId]);
        $this->actingAs(User::findOrFail($sellerId))->get(route('filament.admin.pages.dashboard'))->assertOk()
            ->assertSee(route('operations.orders'), false)->assertDontSee(route('access.index'), false)
            ->assertDontSee(route('access.roles'), false)->assertDontSee(route('operations.stock'), false)->assertDontSee(route('customers.index'), false);
        $this->get(route('operations.orders'))->assertOk()->assertSee('Registrar nota de pedido');

        foreach (['access.index', 'access.roles', 'customers.index', 'operations.stock', 'operations.production.index', 'operations.catalog', 'operations.authorization', 'operations.dispatch.index', 'operations.audit'] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        $this->get(route('operations.snapshot', ['section' => 'stock']))->assertForbidden();
        $this->post(route('operations.receive'), [])->assertForbidden();
    }

    public function test_existing_links_redirect_to_the_same_panel_without_losing_query_or_write_method(): void
    {
        $this->actingAs($this->admin)->get('/gestion/clientes?q=cliente&page=2')->assertRedirect(url('admin/clientes').'?page=2&q=cliente');
        $this->get('/gestion/usuarios')->assertRedirect(route('access.index'));
        $this->post('/gestion/operaciones/customer', ['name' => 'No guardar durante la redirección'])
            ->assertStatus(307)->assertRedirect(route('operations.customer'));
        $this->assertDatabaseCount('business_customers', 0);
        $this->followingRedirects()->get('/gestion/clientes')->assertOk()->assertSee('fi-main-sidebar', false);
    }

    public function test_password_change_invalidates_management_access_before_any_write(): void
    {
        $this->actingAs($this->admin)->get(route('customers.index'))->assertOk();
        DB::table('users')->where('id', $this->admin->id)->update(['password' => Hash::make('new-private-password')]);
        $this->actingAs($this->admin->fresh());
        $this->post(route('customers.store'), ['name' => 'No autorizado', 'cuit' => '20123456789'])->assertRedirect(route('filament.admin.auth.login'));
        $this->assertDatabaseCount('business_customers', 0);
    }
}
