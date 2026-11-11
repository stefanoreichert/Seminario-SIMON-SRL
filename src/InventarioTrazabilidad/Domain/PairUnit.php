<?php

namespace Simon\InventarioTrazabilidad\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final class PairUnit
{
    public function __construct(public readonly string $code, public readonly int $batchId, public readonly int $variantId, private string $state = 'available', private ?int $orderLineId = null)
    {
        if (! preg_match('/\APAR-[A-F0-9]{24}\z/', $code) || ! in_array($state, ['available', 'picked', 'dispatched'], true)) {
            throw new BusinessRuleViolation('El código o estado del par no es válido.');
        }
    }

    public function pick(int $lineId, int $variantId): void
    {
        if ($this->state !== 'available') {
            throw new BusinessRuleViolation('El código ya fue leído para picking o corresponde a un par despachado.');
        }

        if ($variantId !== $this->variantId) {
            throw new BusinessRuleViolation('El par no corresponde al modelo, color y talle del pedido.');
        }

        $this->state = 'picked';
        $this->orderLineId = $lineId;
    }

    public function release(int $lineId): void
    {
        $this->assertSelected($lineId, $this->batchId);
        $this->state = 'available';
        $this->orderLineId = null;
    }

    public function assertSelected(int $lineId, int $batchId): void
    {
        if ($this->state !== 'picked' || $this->orderLineId !== $lineId || $this->batchId !== $batchId) {
            throw new BusinessRuleViolation('El par no está seleccionado para esta línea y lote de despacho.');
        }
    }

    public function dispatch(int $lineId, int $batchId): void
    {
        $this->assertSelected($lineId, $batchId);
        $this->state = 'dispatched';
    }

    public function state(): string
    {
        return $this->state;
    }

    public function orderLineId(): ?int
    {
        return $this->orderLineId;
    }
}
