<?php

namespace Simon\InventarioTrazabilidad\Application;

interface SupplyLots
{
    public function configure(int $anticipationDays): void;

    public function register(string $operationId, int $actorId, string $name, string $lot, string $expiresOn, ?int $productionId): int;

    /** @return array{today:string,anticipation_days:?int,items:list<array<string,mixed>>,page:int,pages:int,total:int} */
    public function overview(int $page): array;
}
