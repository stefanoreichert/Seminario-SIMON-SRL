<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\PairUnit;

interface PairUnits
{
    /** @param array<string,int|string> $request */
    public function replay(string $operationId, int $actorId, array $request): ?int;

    /** @param array<string,int|string> $request */
    public function record(string $operationId, int $actorId, array $request): int;

    public function tracked(int $batchId): bool;

    public function identify(int $batchId, int $quantity, int $actorId): void;

    public function increaseIfTracked(int $batchId, int $quantity): void;

    public function get(string $code): PairUnit;

    public function save(PairUnit $unit, int $actorId, ?int $movementId = null): void;

    public function pickedCount(int $lineId): int;

    /** @return array{items:list<array<string,mixed>>,page:int,pages:int,total:int,selected:list<array<string,mixed>>} */
    public function overview(?int $lineId, ?int $batchId, int $page): array;
}
