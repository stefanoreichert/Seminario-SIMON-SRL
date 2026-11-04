<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\Shared\Application\Actor;

interface InventoryStore
{
    /** @param array<string, int|string|bool> $request */
    public function replay(string $operationId, int $actorId, array $request): ?int;

    /** @return array{id:int, variant_id:int, lot:string, manufactured_on:string, quantity:int} */
    public function stock(int $batchId): array;

    /** @return array{id:int, variant_id:int, lot:string, manufactured_on:string, in_process:int} */
    public function production(int $batchId): array;

    public function receive(int $productionId, int $quantity): int;

    public function opening(int $variantId, string $lot, string $manufacturedOn, int $quantity): int;

    public function decrease(int $batchId, int $quantity): void;

    /** @param array<string, int|string|bool> $request */
    public function record(string $operationId, Actor $actor, string $kind, int $quantity, int $batchId, ?int $orderLineId, array $request, array $authorization = []): int;
}
