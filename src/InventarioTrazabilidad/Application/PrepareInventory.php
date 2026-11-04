<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\MovementRules;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class PrepareInventory
{
    public function __construct(private AccessPolicy $access, private InventorySetup $inventory) {}

    public function variant(int $actorId, string $code, string $model, string $color, string $size): int
    {
        $this->access->require($actorId, 'catalog.manage');

        foreach ([$code, $model, $color, $size] as $value) {
            if (trim($value) === '') {
                throw new BusinessRuleViolation('Completá código, modelo, color y talle.');
            }
        }

        return $this->inventory->variant(trim($code), trim($model), trim($color), trim($size));
    }

    public function production(int $actorId, string $operationId, int $variantId, string $lot, string $manufacturedOn, int $quantity): int
    {
        $this->access->require($actorId, 'production.manage');
        MovementRules::quantity($quantity);
        MovementRules::batch($lot, $manufacturedOn);
        MovementRules::operationId($operationId);

        return $this->inventory->createProduction($operationId, $variantId, trim($lot), $manufacturedOn, $quantity);
    }
}
