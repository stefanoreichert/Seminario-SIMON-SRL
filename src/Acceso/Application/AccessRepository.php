<?php

namespace Simon\Acceso\Application;

interface AccessRepository
{
    /** @param list<string> $permissions */
    public function saveRole(?int $roleId, string $name, array $permissions): int;

    /** @param list<int> $roleIds */
    public function saveUser(?int $userId, string $name, string $email, ?string $password, ?string $salesIdentifier, bool $active, array $roleIds): int;
}
