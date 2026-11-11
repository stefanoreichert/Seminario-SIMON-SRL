<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\ClientesAdministracion\UI\CustomerSearch;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Tests\TestCase;

class CustomerProfilesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Administración de prueba']);
        $this->artisan('access:bootstrap', ['email' => $this->admin->email])->assertSuccessful();
        $this->unauthorized = User::factory()->create();
        $this->customerId = app(ManageCustomers::class)->register(
            $this->admin->id, 'Cliente original', '20-12345678-9', 'Verificación inicial manual', '+54 376 4000000', 'original@example.test',
        );
    }

    /** @return array{name: string, cuit: string, phone: ?string, email: ?string} */
    private function profile(): array
    {
        return ['name' => 'Cliente actualizado', 'cuit' => '20-12345678-9', 'phone' => '+54 376 4555555', 'email' => 'contacto@example.test'];
    }

    public function test_livewire_customer_search_filters_and_rechecks_access(): void
    {
        app(ManageCustomers::class)->register($this->admin->id, 'Comercio diferente', '30876543210', '');
        $user = User::factory()->create();
        $role = DB::table('access_roles')->insertGetId(['name' => 'Clientes temporal']);
        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => 'clients.manage']);
        DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $role]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(CustomerSearch::class)
            ->set('query', 'original')->call('search')
            ->assertSee('Cliente original')->assertDontSee('Comercio diferente')
            ->call('clear')->assertSee('Cliente original')->assertSee('Comercio diferente');
        DB::table('access_role_permissions')->where('role_id', $role)->delete();
        $component->call('search')->assertForbidden();
    }

    public function test_registration_and_profile_retrieval_include_contacts_and_initial_credit_observation(): void
    {
        $this->actingAs($this->admin)->get(route('customers.create'))->assertOk()->assertSee('Registrar cliente');
        $this->from(route('customers.create'))->post(route('customers.store'), [
            'name' => '  Comercio nuevo  ', 'cuit' => ' 30-87654321-0 ', 'phone' => ' +54 376 4777777 ',
            'email' => ' nuevo@example.test ', 'credit_history' => '  Carga manual inicial  ',
        ])->assertRedirect(route('customers.create'))->assertSessionHasNoErrors();
        $id = DB::table('business_customers')->where('cuit', '30876543210')->value('id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('business_customers', [
            'id' => $id, 'name' => 'Comercio nuevo', 'phone' => '+54 376 4777777', 'email' => 'nuevo@example.test', 'credit_history' => 'Carga manual inicial',
        ]);
        $response = $this->get(route('customers.show', $id))->assertOk()
            ->assertSee('Comercio nuevo')->assertSee('30876543210')->assertSee('+54 376 4777777')
            ->assertSee('nuevo@example.test')->assertSee('Carga manual inicial')->assertSee($this->admin->name)->assertSee('Fecha y hora UTC');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('customers.index'))->assertOk()->assertSee('Comercio nuevo')->assertSee('nuevo@example.test');
    }

    public function test_profile_edits_keep_the_same_cuit_and_do_not_overwrite_credit_or_another_customer(): void
    {
        $otherId = app(ManageCustomers::class)->register($this->admin->id, 'Otro cliente', '30-87654321-0', 'Historial de otro cliente');
        $this->actingAs($this->admin)->from(route('customers.show', $this->customerId))
            ->put(route('customers.update', $this->customerId), [
                ...$this->profile(), 'credit_history' => 'No debe reemplazar el historial desde el perfil', 'customer_id' => $otherId,
            ])->assertRedirect(route('customers.show', $this->customerId))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_customers', [
            'id' => $this->customerId, 'name' => 'Cliente actualizado', 'cuit' => '20123456789',
            'phone' => '+54 376 4555555', 'email' => 'contacto@example.test', 'credit_history' => 'Verificación inicial manual',
        ]);
        $this->assertDatabaseHas('business_customers', ['id' => $otherId, 'name' => 'Otro cliente', 'credit_history' => 'Historial de otro cliente']);
        $this->assertDatabaseCount('customer_credit_entries', 2);
        $this->get(route('customers.show', $this->customerId))->assertOk()->assertSee('contacto@example.test')->assertSee('Verificación inicial manual')->assertDontSee('Historial de otro cliente');

        $this->put(route('customers.update', $this->customerId), [...$this->profile(), 'cuit' => '27-12345678-0', 'phone' => '', 'email' => ''])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'cuit' => '27123456780', 'phone' => null, 'email' => null]);
    }

    public function test_credit_updates_are_manual_and_recover_all_observations_with_actor_and_date(): void
    {
        $this->freezeTime();
        $this->actingAs($this->admin)->from(route('customers.show', $this->customerId));

        foreach (['Crédito revisado por administración', 'Condiciones actualizadas manualmente'] as $note) {
            $this->post(route('customers.credit', $this->customerId), ['credit_history' => $note])
                ->assertRedirect(route('customers.show', $this->customerId))->assertSessionHasNoErrors();
        }

        $profile = app(ManageCustomers::class)->profile($this->admin->id, $this->customerId);
        $this->assertSame('Condiciones actualizadas manualmente', $profile['credit_history']);
        $this->assertSame([
            'Condiciones actualizadas manualmente', 'Crédito revisado por administración', 'Verificación inicial manual',
        ], array_column($profile['credit_entries'], 'note'));
        $this->assertSame($this->admin->id, $profile['credit_entries'][0]['actor_id']);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $profile['credit_entries'][0]['recorded_at']);
        $this->get(route('customers.show', $this->customerId))->assertOk()
            ->assertSee('Verificación inicial manual')->assertSee('Crédito revisado por administración')
            ->assertSee('Condiciones actualizadas manualmente')->assertSee($this->admin->name);
        $this->assertDatabaseCount('customer_credit_entries', 3);
    }

    public function test_duplicate_cuit_is_rejected_on_registration_and_edit_without_partial_changes(): void
    {
        $otherId = app(ManageCustomers::class)->register($this->admin->id, 'Otro cliente', '30-87654321-0', 'Otra evaluación');
        $this->actingAs($this->admin)->from(route('customers.create'))->post(route('customers.store'), [
            ...$this->profile(), 'cuit' => '20123456789',
        ])->assertSessionHasErrors('operation');
        $this->from(route('customers.show', $this->customerId))->put(route('customers.update', $this->customerId), [
            ...$this->profile(), 'cuit' => '30 - 87654321 - 0',
        ])->assertSessionHasErrors('operation')->assertSessionHasInput('name', 'Cliente actualizado');
        $this->assertDatabaseCount('business_customers', 2);
        $this->assertDatabaseCount('customer_credit_entries', 2);
        $this->assertDatabaseHas('business_customers', [
            'id' => $this->customerId, 'name' => 'Cliente original', 'cuit' => '20123456789',
            'phone' => '+54 376 4000000', 'email' => 'original@example.test', 'credit_history' => 'Verificación inicial manual',
        ]);
        $this->assertDatabaseHas('business_customers', ['id' => $otherId, 'cuit' => '30876543210']);
    }

    public function test_invalid_http_profile_fields_and_blank_credit_are_rejected(): void
    {
        $this->actingAs($this->admin)->from(route('customers.show', $this->customerId));

        foreach ([['name', ''], ['cuit', ''], ['name', str_repeat('a', 256)], ['cuit', str_repeat('1', 33)], ['phone', str_repeat('1', 51)], ['email', 'no-es-un-correo']] as [$field, $value]) {
            $this->put(route('customers.update', $this->customerId), [$field => $value, ...array_diff_key($this->profile(), [$field => true])])
                ->assertSessionHasErrors($field);
        }

        $this->put(route('customers.update', $this->customerId), [...$this->profile(), 'cuit' => ' - - '])->assertSessionHasErrors('operation');
        $this->post(route('customers.credit', $this->customerId), ['credit_history' => '   '])->assertSessionHasErrors('credit_history');
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'name' => 'Cliente original', 'email' => 'original@example.test']);
        $this->assertDatabaseCount('customer_credit_entries', 1);
    }

    public function test_application_rules_cannot_be_bypassed_by_direct_calls(): void
    {
        $customers = app(ManageCustomers::class);
        $invalidOperations = [
            fn () => $customers->register($this->admin->id, 'Nombre', ' - ', ''),
            fn () => $customers->register($this->admin->id, '', '123', ''),
            fn () => $customers->update($this->admin->id, $this->customerId, 'Nombre', '20123456789', null, 'correo inválido'),
            fn () => $customers->update($this->admin->id, $this->customerId, 'Nombre', '20123456789', str_repeat('1', 51), null),
            fn () => $customers->updateCredit($this->admin->id, $this->customerId, '  '),
            fn () => $customers->updateCredit($this->admin->id, $this->customerId, str_repeat('a', 10001)),
        ];

        foreach ($invalidOperations as $operation) {
            try {
                $operation();
                $this->fail('La regla debe aplicarse también fuera del controlador HTTP.');
            } catch (BusinessRuleViolation $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        $this->assertDatabaseCount('business_customers', 1);
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'name' => 'Cliente original']);
        $this->assertDatabaseCount('customer_credit_entries', 1);
    }

    public function test_http_permissions_cover_queries_forms_and_direct_writes(): void
    {
        $this->actingAs($this->unauthorized);

        foreach (['customers.index', 'customers.create', 'customers.show'] as $route) {
            $this->get(route($route, $route === 'customers.show' ? $this->customerId : []))->assertForbidden();
        }

        $this->post(route('customers.store'), $this->profile())->assertForbidden();
        $this->put(route('customers.update', $this->customerId), $this->profile())->assertForbidden();
        $this->post(route('customers.credit', $this->customerId), ['credit_history' => 'Cambio no permitido'])->assertForbidden();
        $this->post(route('operations.customer'), $this->profile())->assertForbidden();
        $this->post(route('operations.credit'), ['customer_id' => $this->customerId, 'credit_history' => 'Cambio no permitido'])->assertForbidden();
        $this->get(route('filament.admin.pages.dashboard'))->assertOk()->assertDontSee(route('customers.index'), false);
        DB::table('users')->where('id', $this->admin->id)->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('customers.index'))->assertForbidden();
        $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'name' => 'Cliente original']);
        $this->assertDatabaseCount('customer_credit_entries', 1);
    }

    public function test_application_permissions_cover_reading_and_writing_profiles_and_history(): void
    {
        $customers = app(ManageCustomers::class);

        foreach ([
            fn () => $customers->search($this->unauthorized->id),
            fn () => $customers->profile($this->unauthorized->id, $this->customerId),
            fn () => $customers->register($this->unauthorized->id, 'Sin permiso', '30876543210', ''),
            fn () => $customers->update($this->unauthorized->id, $this->customerId, 'Sin permiso', '20123456789', null, null),
            fn () => $customers->updateCredit($this->unauthorized->id, $this->customerId, 'Sin permiso'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Cada caso de uso debe comprobar el permiso de clientes.');
            } catch (PermissionDenied) {
                $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'name' => 'Cliente original']);
            }
        }

        $this->assertDatabaseCount('business_customers', 1);
        $this->assertDatabaseCount('customer_credit_entries', 1);
    }

    public function test_search_and_pagination_reach_all_customers_and_treat_wildcards_as_text(): void
    {
        $rows = [];

        for ($index = 0; $index < 510; $index++) {
            $rows[] = ['name' => sprintf('Comercio %03d', $index), 'cuit' => '99'.str_pad((string) $index, 9, '0', STR_PAD_LEFT), 'credit_history' => ''];
        }

        DB::table('business_customers')->insert($rows);
        $lastId = app(ManageCustomers::class)->register($this->admin->id, 'Zeta 50%_especial', '30-87654321-0', '', '+54 376 4888888', 'ultimo@example.test');
        $this->actingAs($this->admin);

        foreach (['Zeta', '30-87654321-0', '4888888', 'ultimo@example.test', '%_'] as $query) {
            $this->get(route('customers.index', ['q' => $query]))->assertOk()
                ->assertViewHas('total', 1)->assertSee(route('customers.show', $lastId), false)->assertSee('Zeta 50%_especial');
        }

        $this->get(route('customers.index', ['q' => 'Comercio', 'page' => 2]))->assertOk()
            ->assertViewHas('total', 510)->assertViewHas('page', 2)->assertViewHas('items', fn (array $items): bool => count($items) === 25)
            ->assertSee('Comercio 025')->assertDontSee('Comercio 000')->assertSee('page=3', false);
        $this->get(route('customers.index', ['q' => 'sin coincidencias']))->assertOk()->assertViewHas('total', 0)->assertSee('No hay clientes para esta búsqueda.');
    }

    public function test_unknown_customer_cannot_be_updated_or_receive_credit_entries(): void
    {
        $customers = app(ManageCustomers::class);
        $this->assertNull($customers->profile($this->admin->id, 999999));
        $this->actingAs($this->admin)->get(route('customers.show', 999999))->assertNotFound();

        foreach ([
            fn () => $customers->update($this->admin->id, 999999, 'Inexistente', '123', null, null),
            fn () => $customers->updateCredit($this->admin->id, 999999, 'No debe registrarse'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('El cliente inexistente debe rechazarse.');
            } catch (BusinessRuleViolation $exception) {
                $this->assertSame('El cliente no existe.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('business_customers', 1);
        $this->assertDatabaseCount('customer_credit_entries', 1);
    }

    public function test_credit_entry_failure_rolls_back_the_current_observation_and_customer_registration(): void
    {
        DB::unprepared("CREATE TRIGGER reject_credit_entry BEFORE INSERT ON customer_credit_entries BEGIN SELECT RAISE(ABORT, 'Fallo de historial simulado'); END");
        $customers = app(ManageCustomers::class);

        foreach ([
            fn () => $customers->updateCredit($this->admin->id, $this->customerId, 'Cambio que debe revertirse'),
            fn () => $customers->register($this->admin->id, 'Alta que debe revertirse', '30876543210', 'Cambio que debe revertirse'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Un fallo del historial debe revertir toda la escritura.');
            } catch (QueryException) {
                $this->assertDatabaseHas('business_customers', ['id' => $this->customerId, 'credit_history' => 'Verificación inicial manual']);
                $this->assertDatabaseCount('business_customers', 1);
                $this->assertDatabaseCount('customer_credit_entries', 1);
            }
        }
    }

    public function test_contact_migration_preserves_legacy_profiles_and_credit_history(): void
    {
        $migration = require base_path('src/ClientesAdministracion/Infrastructure/Database/Migrations/2026_11_04_210000_add_customer_contact_fields.php');
        $migration->down();
        $migration->up();
        $profile = app(ManageCustomers::class)->profile($this->admin->id, $this->customerId);
        $this->assertSame($this->customerId, $profile['id']);
        $this->assertSame('20123456789', $profile['cuit']);
        $this->assertNull($profile['phone']);
        $this->assertNull($profile['email']);
        $this->assertSame('Verificación inicial manual', $profile['credit_history']);
        $this->assertCount(1, $profile['credit_entries']);
        $this->assertSame('Verificación inicial manual', $profile['credit_entries'][0]['note']);
    }

    public function test_profile_and_credit_content_are_escaped_when_displayed(): void
    {
        $customers = app(ManageCustomers::class);
        $customers->update($this->admin->id, $this->customerId, '<script>alert("nombre")</script>', '20123456789', '<b>teléfono</b>', null);
        $customers->updateCredit($this->admin->id, $this->customerId, '<script>alert("historial")</script>');
        $this->actingAs($this->admin)->get(route('customers.show', $this->customerId))->assertOk()
            ->assertDontSee('<script>alert("nombre")</script>', false)->assertDontSee('<script>alert("historial")</script>', false)
            ->assertSee('&lt;script&gt;', false);
        $this->get(route('customers.index'))->assertOk()->assertDontSee('<script>alert("nombre")</script>', false)->assertDontSee('<b>teléfono</b>', false);
    }
}
