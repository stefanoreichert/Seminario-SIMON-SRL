<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\MovementRules;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class ManagePicking
{
    public function __construct(private AccessPolicy $access, private UnitOfWork $transactions, private PairUnits $pairs, private InventoryStore $inventory, private OrderFulfillment $orders) {}

    public function identify(int $actorId, string $operationId, int $batchId): int
    {
        $this->access->require($actorId, 'inventory.receive');
        MovementRules::operationId($operationId);
        $request = ['kind' => 'identify', 'batch_id' => $batchId];

        return $this->transactions->run(function () use ($actorId, $operationId, $batchId, $request): int {
            $previous = $this->pairs->replay($operationId, $actorId, $request);

            if ($previous !== null) {
                return $previous;
            }

            if ($this->pairs->tracked($batchId)) {
                throw new BusinessRuleViolation('El lote ya tiene identificación individual.');
            }

            $stock = $this->inventory->stock($batchId);
            MovementRules::quantity($stock['quantity']);
            $this->pairs->identify($batchId, $stock['quantity'], $actorId);

            return $this->pairs->record($operationId, $actorId, $request);
        });
    }

    public function scan(int $actorId, string $operationId, int $lineId, string $code, bool $release = false): int
    {
        $this->access->require($actorId, 'inventory.dispatch');
        MovementRules::operationId($operationId);
        $code = trim($code);
        $request = ['kind' => $release ? 'release' : 'pick', 'line_id' => $lineId, 'code' => $code];

        return $this->transactions->run(function () use ($actorId, $operationId, $lineId, $code, $release, $request): int {
            $previous = $this->pairs->replay($operationId, $actorId, $request);

            if ($previous !== null) {
                return $previous;
            }

            $unit = $this->pairs->get($code);

            if ($release) {
                $unit->release($lineId);
            } else {
                $line = $this->orders->line($lineId);

                if ($this->pairs->pickedCount($lineId) >= $line['quantity'] - $line['dispatched']) {
                    throw new BusinessRuleViolation('Ya se seleccionaron los pares pendientes de esta línea.');
                }

                $unit->pick($lineId, $line['variant_id']);
            }

            $this->pairs->save($unit, $actorId);

            return $this->pairs->record($operationId, $actorId, $request);
        });
    }

    /** @return array<string,mixed> */
    public function overview(int $actorId, ?int $lineId = null, ?int $batchId = null, int $page = 1): array
    {
        $permission = $this->access->allows($actorId, 'inventory.dispatch') ? 'inventory.dispatch' : ($this->access->allows($actorId, 'inventory.receive') ? 'inventory.receive' : 'inventory.view');
        $this->access->require($actorId, $permission);

        if ($page < 1 || ($lineId !== null && $lineId < 1) || ($batchId !== null && $batchId < 1)) {
            throw new BusinessRuleViolation('La línea, lote o página no es válida.');
        }

        return $this->pairs->overview($lineId, $batchId, $page);
    }
}
