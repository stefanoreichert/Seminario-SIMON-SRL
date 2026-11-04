<?php

namespace Simon\Acceso\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Simon\Acceso\Application\ManageAccess;
use Simon\Acceso\Domain\Permissions;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\HandlesOperations;

final class AccessController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, AccessPolicy $access): View
    {
        abort_unless($access->allows($request->user()->id, 'access.manage'), 403);

        return view('acceso::index', [
            'roles' => DB::table('access_roles')->orderBy('name')->get(),
            'users' => DB::table('users')->select('id', 'name', 'email', 'sales_identifier', 'is_active')->orderBy('name')->get(),
            'assignments' => DB::table('access_user_roles')->get()->groupBy('user_id'),
            'rolePermissions' => DB::table('access_role_permissions')->get()->groupBy('role_id'),
            'permissionLabels' => Permissions::all(),
        ]);
    }

    public function role(Request $request, ManageAccess $manage): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['nullable', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:100'],
            'permissions' => ['array'], 'permissions.*' => ['string', 'max:100'],
        ]);

        return $this->perform(fn (): int => $manage->saveRole($request->user()->id, isset($data['role_id']) ? (int) $data['role_id'] : null, $data['name'], $data['permissions'] ?? []), 'Rol y permisos guardados.');
    }

    public function user(Request $request, ManageAccess $manage): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'], 'password' => ['nullable', 'string', 'min:12', 'max:255', 'confirmed'],
            'sales_identifier' => ['nullable', 'in:S,G,F'], 'active' => ['boolean'],
            'role_ids' => ['array'], 'role_ids.*' => ['integer', 'min:1'],
        ]);

        return $this->perform(fn (): int => $manage->saveUser(
            $request->user()->id, isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['name'], $data['email'], $data['password'] ?? null, $data['sales_identifier'] ?? null,
            $request->boolean('active'), array_map('intval', $data['role_ids'] ?? []),
        ), 'Usuario y roles guardados.');
    }
}
