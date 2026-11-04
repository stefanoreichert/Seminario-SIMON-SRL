<?php

namespace Simon\InventarioTrazabilidad\Application;

interface InventorySetup
{
    public function variant(string $code, string $model, string $color, string $size): int;

    public function createProduction(string $operationId, int $variantId, string $lot, string $manufacturedOn, int $quantity): int;
}
