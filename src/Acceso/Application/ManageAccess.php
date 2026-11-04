<?php

namespace Simon\Acceso\Application;

use Simon\Acceso\Domain\Permissions;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class ManageAccess
{
    public function __construct(private AccessPolicy $access, private AccessRepository $repository) {}

    /** @param list<string> $permissions */
    public function saveRole(int $actorId, ?int $roleId, string $name, array $permissions): int
    {
        $this->access->require($actorId, 'access.manage');

        if (trim($name) === '' || array_diff($permissions, array_keys(Permissions::all())) !== []) {
            throw new BusinessRuleViolation('Revisá el nombre del rol y sus permisos.');
        }

        return $this->repository->saveRole($roleId, trim($name), array_values(array_unique($permissions)));
    }

    /** @param list<int> $roleIds */
    public function saveUser(int $actorId, ?int $userId, string $name, string $email, ?string $password, ?string $salesIdentifier, bool $active, array $roleIds): int
    {
        $this->access->require($actorId, 'access.manage');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || trim($name) === '') {
            throw new BusinessRuleViolation('Ingresá un nombre y un correo válido.');
        }

        if (($userId === null || $password !== null) && strlen($password ?? '') < 12) {
            throw new BusinessRuleViolation('La contraseña debe tener al menos 12 caracteres.');
        }

        if ($salesIdentifier !== null && ! in_array($salesIdentifier, ['S', 'G', 'F'], true)) {
            throw new BusinessRuleViolation('El identificador de vendedor debe ser S, G o F.');
        }

        return $this->repository->saveUser($userId, trim($name), strtolower(trim($email)), $password, $salesIdentifier, $active, array_values(array_unique($roleIds)));
    }
}
