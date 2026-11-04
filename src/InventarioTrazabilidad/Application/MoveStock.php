<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\MovementRules;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class MoveStock
{
    public function __construct(
        private AccessPolicy $access,
        private UnitOfWork $transactions,
        private InventoryStore $inventory,
        private OrderFulfillment $orders,
        private DispatchAuthorization $authorizations,
    ) {}

    public function receive(int $actorId, string $operationId, int $productionId, int $quantity): int
    {
        $actor = $this->access->require($actorId, 'inventory.receive');
        MovementRules::operationId($operationId);
        MovementRules::quantity($quantity);
        $request = ['kind' => 'receipt', 'production_id' => $productionId, 'quantity' => $quantity];

        return $this->transactions->run(function () use ($actor, $operationId, $productionId, $quantity, $request): int {
            $previous = $this->inventory->replay($operationId, $actor->id, $request);

            if ($previous !== null) {
                return $previous;
            }

            $production = $this->inventory->production($productionId);
            MovementRules::batch($production['lot'], $production['manufactured_on']);
            $batchId = $this->inventory->receive($productionId, $quantity);

            return $this->inventory->record($operationId, $actor, 'receipt', $quantity, $batchId, null, $request);
        });
    }

    public function opening(int $actorId, string $operationId, int $variantId, string $lot, string $manufacturedOn, int $quantity): int
    {
        $actor = $this->access->require($actorId, 'inventory.opening');
        MovementRules::operationId($operationId);
        MovementRules::quantity($quantity);
        MovementRules::batch($lot, $manufacturedOn);
        $request = ['kind' => 'opening', 'variant_id' => $variantId, 'lot' => trim($lot), 'manufactured_on' => $manufacturedOn, 'quantity' => $quantity];

        return $this->transactions->run(function () use ($actor, $operationId, $variantId, $lot, $manufacturedOn, $quantity, $request): int {
            $previous = $this->inventory->replay($operationId, $actor->id, $request);

            if ($previous !== null) {
                return $previous;
            }

            $batchId = $this->inventory->opening($variantId, trim($lot), $manufacturedOn, $quantity);

            return $this->inventory->record($operationId, $actor, 'opening', $quantity, $batchId, null, $request);
        });
    }

    public function dispatch(int $actorId, string $operationId, int $orderLineId, int $batchId, int $quantity, bool $ownerWithdrawal = false): int
    {
        $actor = $this->access->require($actorId, 'inventory.dispatch');
        MovementRules::operationId($operationId);
        MovementRules::quantity($quantity);
        $request = ['kind' => 'dispatch', 'order_line_id' => $orderLineId, 'batch_id' => $batchId, 'quantity' => $quantity, 'owner_withdrawal' => $ownerWithdrawal];

        return $this->transactions->run(function () use ($actor, $operationId, $orderLineId, $batchId, $quantity, $ownerWithdrawal, $request): int {
            $previous = $this->inventory->replay($operationId, $actor->id, $request);

            if ($previous !== null) {
                return $previous;
            }

            $line = $this->orders->line($orderLineId);
            $authorization = $this->authorizations->assertAuthorized($line['order_id']);
            $stock = $this->inventory->stock($batchId);
            MovementRules::batch($stock['lot'], $stock['manufactured_on']);

            if ($stock['variant_id'] !== $line['variant_id']) {
                throw new BusinessRuleViolation('El lote no corresponde al artículo del pedido.');
            }

            $this->orders->dispatch($orderLineId, $quantity);
            $this->inventory->decrease($batchId, $quantity);

            return $this->inventory->record($operationId, $actor, $ownerWithdrawal ? 'owner_withdrawal' : 'dispatch', $quantity, $batchId, $orderLineId, $request, $authorization);
        });
    }
}
