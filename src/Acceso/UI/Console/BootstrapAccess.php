<?php

namespace Simon\Acceso\UI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Simon\Acceso\Domain\Permissions;

class BootstrapAccess extends Command
{
    protected $signature = 'access:bootstrap {email : Correo de un usuario existente}';

    protected $description = 'Asignar el primer administrador de permisos, solo si todavía no existe uno activo';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            DB::table('access_assignment_lock')->where('id', 1)->increment('revision');
            $alreadyConfigured = DB::table('users')->where('is_active', true)
                ->join('access_user_roles', 'access_user_roles.user_id', '=', 'users.id')
                ->join('access_role_permissions', 'access_role_permissions.role_id', '=', 'access_user_roles.role_id')
                ->where('permission', 'access.manage')->exists();

            if ($alreadyConfigured) {
                $this->error('Los permisos ya están configurados. Usá el módulo de gestión.');

                return self::FAILURE;
            }

            $user = DB::table('users')->where('email', $this->argument('email'))->where('is_active', true)->first();

            if ($user === null) {
                $this->error('No existe un usuario activo con ese correo.');

                return self::FAILURE;
            }

            $roleId = DB::table('access_roles')->insertGetId(['name' => 'Administración inicial']);

            foreach (array_keys(Permissions::all()) as $permission) {
                DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
            }

            DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);
            $this->info('Administrador inicial asignado. Configurá los roles de cada persona desde Usuarios y permisos.');

            return self::SUCCESS;
        }, 5);
    }
}
