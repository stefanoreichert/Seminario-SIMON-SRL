<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Infrastructure\Persistence\User;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\ManagementActions;
use Tests\TestCase;

class UserManagementViewTest extends TestCase
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

    public function test_list_and_selected_forms_show_user_details_and_roles(): void
    {
        $role = app(ManageAccess::class)->saveRole($this->admin->id, null, 'Depósito de prueba', ['inventory.view']);
        $user = app(ManageAccess::class)->saveUser($this->admin->id, null, 'Usuario de prueba', 'user@example.test', 'long-test-password', 'S', false, [$role]);

        $this->get(route('access.index'))->assertOk()->assertSeeInOrder(['Nombre', 'Correo', 'Roles', 'Estado', 'Acciones'])
            ->assertSee('Usuario de prueba')->assertSee('user@example.test')->assertSee('Depósito de prueba')->assertSee('Inactivo')
            ->assertSee(route('access.index', ['editar' => $user]), false)->assertDontSee('Roles asignados');
        $this->get(route('access.index', ['editar' => $user]))->assertOk()->assertSee('Editar usuario: Usuario de prueba')
            ->assertSee('Roles asignados')->assertSee('desmarcar para deshabilitar');
        $this->get(route('access.index', ['nuevo' => 1]))->assertOk()->assertSee('Repetir contraseña');
        $this->get(route('access.index', ['editar' => 999999]))->assertNotFound();
        $this->get(route('access.index', ['editar' => ['1']]))->assertNotFound();
    }

    public function test_livewire_user_edits_change_roles_and_state_without_losing_identity_or_password(): void
    {
        $manager = app(ManageAccess::class);
        $firstRole = $manager->saveRole($this->admin->id, null, 'Primer rol', ['inventory.view']);
        $secondRole = $manager->saveRole($this->admin->id, null, 'Segundo rol', ['orders.create']);
        $id = $manager->saveUser($this->admin->id, null, 'Usuario anterior', 'user@example.test', 'long-test-password', 'S', true, [$firstRole]);
        $fields = ['user_id' => $id, 'name' => 'Usuario actualizado', 'email' => 'user@example.test', 'password' => '', 'password_confirmation' => '', 'sales_identifier' => 'S', 'role_ids' => [$secondRole]];

        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios', 'POST', $fields)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $id, 'name' => 'Usuario actualizado', 'is_active' => false, 'sales_identifier' => 'S']);
        $this->assertTrue(Hash::check('long-test-password', DB::table('users')->where('id', $id)->value('password')));
        $this->assertDatabaseMissing('access_user_roles', ['user_id' => $id, 'role_id' => $firstRole]);
        $this->assertDatabaseHas('access_user_roles', ['user_id' => $id, 'role_id' => $secondRole]);

        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios', 'POST', [...$fields, 'active' => '1'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $id, 'is_active' => true]);
    }

    public function test_livewire_creates_a_user_with_a_hashed_password_and_assigned_role(): void
    {
        $role = app(ManageAccess::class)->saveRole($this->admin->id, null, 'Rol nuevo', ['orders.create']);
        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios', 'POST', [
            'name' => 'Cuenta nueva', 'email' => 'new@example.test', 'password' => 'long-test-password',
            'password_confirmation' => 'long-test-password', 'active' => '1', 'role_ids' => [$role],
        ])->assertRedirect(route('access.index'));
        $user = DB::table('users')->where('email', 'new@example.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('long-test-password', $user->password));
        $this->assertDatabaseHas('access_user_roles', ['user_id' => $user->id, 'role_id' => $role]);
    }

    public function test_livewire_denies_unauthorized_changes_and_protects_the_last_administrator(): void
    {
        $fields = ['user_id' => $this->admin->id, 'name' => $this->admin->name, 'email' => $this->admin->email, 'role_ids' => []];
        $component = Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios', 'POST', $fields);
        $this->assertNotEmpty($component->get('messages')['operation']);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'is_active' => true]);

        $this->actingAs(User::factory()->create());
        $this->get(route('access.index'))->assertForbidden();
        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios', 'POST', $fields)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'is_active' => true]);
    }

    public function test_roles_are_managed_from_the_same_panel_and_livewire_applies_permission_changes(): void
    {
        $role = app(ManageAccess::class)->saveRole($this->admin->id, null, 'Operación', ['inventory.view']);
        $user = app(ManageAccess::class)->saveUser($this->admin->id, null, 'Operador', 'operator@example.test', 'long-test-password', null, true, [$role]);

        $this->get(route('access.index'))->assertOk()->assertSee('Usuarios, roles y permisos')->assertSee(route('access.roles'), false);
        $this->get(route('access.roles'))->assertOk()->assertSee('Usuarios, roles y permisos')->assertSee('Operación')->assertSee('Editar permisos');
        $this->get(route('access.roles', ['editar' => $role]))->assertOk()->assertSee('Editar rol: Operación')->assertSee('permissions[]', false);
        $this->get(route('access.roles', ['nuevo' => 1]))->assertOk()->assertSee('Nombre del rol');
        $this->get(route('access.roles', ['editar' => 999999]))->assertNotFound();

        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios/roles', 'POST', [
            'role_id' => $role, 'name' => 'Operación actualizada', 'permissions' => ['orders.create'],
        ])->assertRedirect(route('access.roles'));
        $this->assertDatabaseHas('access_roles', ['id' => $role, 'name' => 'Operación actualizada']);
        $this->assertTrue(app(AccessPolicy::class)->allows($user, 'orders.create'));
        $this->assertFalse(app(AccessPolicy::class)->allows($user, 'inventory.view'));

        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios/roles', 'POST', [
            'name' => 'Rol creado', 'permissions' => ['inventory.view'],
        ])->assertRedirect(route('access.roles'));
        $this->assertDatabaseHas('access_roles', ['name' => 'Rol creado']);

        $adminRole = DB::table('access_user_roles')->where('user_id', $this->admin->id)->value('role_id');
        $component = Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios/roles', 'POST', [
            'role_id' => $adminRole, 'name' => 'Administrador', 'permissions' => [],
        ]);
        $this->assertNotEmpty($component->get('messages')['operation']);
        $this->assertTrue(app(AccessPolicy::class)->allows($this->admin->id, 'access.manage'));
        $this->flushSession();
        $this->actingAs(User::findOrFail($user));
        $this->get(route('access.roles'))->assertForbidden();
        Livewire::test(ManagementActions::class)->call('submit', '/admin/usuarios/roles', 'POST', ['name' => 'Escalamiento', 'permissions' => ['access.manage']])->assertForbidden();
    }
}
