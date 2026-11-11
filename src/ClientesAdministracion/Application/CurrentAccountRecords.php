<?php

namespace Simon\ClientesAdministracion\Application;

use Closure;

interface CurrentAccountRecords
{
    public function run(Closure $operation): mixed;

    /** @return array{id:int,customer_id:int,currency:string,minor_scale:int,balance_minor:int,initialized:bool}|null */
    public function locked(int $customerId, string $currency): ?array;

    public function create(int $customerId, string $currency, int $scale): int;

    public function replay(string $operationId, int $actorId, string $digest): ?int;

    public function append(int $accountId, int $actorId, string $operationId, string $kind, int $deltaMinor, int $balanceMinor, string $source, string $note, string $digest): int;

    /** @return array{account:?array<string,mixed>,entries:array{items:list<array<string,mixed>>,page:int,pages:int,total:int}} */
    public function report(int $customerId, string $currency, int $page): array;
}
