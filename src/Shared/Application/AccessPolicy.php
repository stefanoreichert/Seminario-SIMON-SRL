<?php

namespace Simon\Shared\Application;

interface AccessPolicy
{
    public function allows(int $userId, string $permission): bool;

    public function require(int $userId, string $permission): Actor;
}
