<?php

namespace Simon\Acceso\Infrastructure;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Simon\Acceso\Application\AccessRepository;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\Actor;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;

final class SqlAccess implements AccessPolicy, AccessRepository
{
    public function allows(int $userId, string $permission): bool
    {
        if (! Schema::hasTable('access_user_roles')) {
            return false;
        }

        return DB::table('users')->where('users.id', $userId)->where('users.is_active', true)
            ->join('access_user_roles', 'access_user_roles.user_id', '=', 'users.id')
            ->join('access_role_permissions', 'access_role_permissions.role_id', '=', 'access_user_roles.role_id')
            ->where('access_role_permissions.permission', $permission)->exists();
    }

    public function require(int $userId, string $permission): Actor
    {
        if (! $this->allows($userId, $permission)) {
            throw new PermissionDenied('No tenés permiso para realizar esta operación.');
        }

        $user = DB::table('users')->where('id', $userId)->first();

        return new Actor($user->id, $user->name, $user->sales_identifier);
    }

    private function lockAssignments(): void
    {
        DB::table('access_assignment_lock')->where('id', 1)->increment('revision');
    }

    private function protectLastAdministrator(): void
    {
        $exists = DB::table('users')->where('users.is_active', true)
            ->join('access_user_roles', 'access_user_roles.user_id', '=', 'users.id')
            ->join('access_role_permissions', 'access_role_permissions.role_id', '=', 'access_user_roles.role_id')
            ->where('access_role_permissions.permission', 'access.manage')->exists();

        if (! $exists) {
            throw new BusinessRuleViolation('Debe quedar al menos un usuario activo que gestione los permisos.');
        }
    }

    public function saveRole(?int $roleId, string $name, array $permissions): int
    {
        try {
            return DB::transaction(function () use ($roleId, $name, $permissions): int {
                $this->lockAssignments();

                if ($roleId === null) {
                    $roleId = DB::table('access_roles')->insertGetId(['name' => $name]);
                } elseif (! DB::table('access_roles')->where('id', $roleId)->exists()) {
                    throw new BusinessRuleViolation('El rol no existe.');
                } else {
                    DB::table('access_roles')->where('id', $roleId)->update(['name' => $name]);
                }

                DB::table('access_role_permissions')->where('role_id', $roleId)->delete();

                foreach ($permissions as $permission) {
                    DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
                }

                $this->protectLastAdministrator();

                return $roleId;
            }, 5);
        } catch (QueryException $exception) {
            throw new BusinessRuleViolation('No se pudo guardar el rol; revisá que el nombre no esté repetido.', previous: $exception);
        }
    }

    public function saveUser(?int $userId, string $name, string $email, ?string $password, ?string $salesIdentifier, bool $active, array $roleIds): int
    {
        try {
            return DB::transaction(function () use ($userId, $name, $email, $password, $salesIdentifier, $active, $roleIds): int {
                $this->lockAssignments();

                if (DB::table('access_roles')->whereIn('id', $roleIds)->count() !== count($roleIds)) {
                    throw new BusinessRuleViolation('Uno de los roles seleccionados no existe.');
                }

                $values = ['name' => $name, 'email' => $email, 'sales_identifier' => $salesIdentifier, 'is_active' => $active, 'updated_at' => now()];

                if ($password !== null) {
                    $values['password'] = Hash::make($password);
                }

                if ($userId === null) {
                    $values['created_at'] = now();
                    $userId = DB::table('users')->insertGetId($values);
                } else {
                    if (! DB::table('users')->where('id', $userId)->exists()) {
                        throw new BusinessRuleViolation('El usuario no existe.');
                    }

                    $values['remember_token'] = null;
                    DB::table('users')->where('id', $userId)->update($values);
                    DB::table('sessions')->where('user_id', $userId)->delete();
                }

                DB::table('access_user_roles')->where('user_id', $userId)->delete();

                foreach ($roleIds as $roleId) {
                    DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
                }

                $this->protectLastAdministrator();

                return $userId;
            }, 5);
        } catch (QueryException $exception) {
            throw new BusinessRuleViolation('Revisá que el correo y el identificador del vendedor no estén repetidos.', previous: $exception);
        }
    }
}
