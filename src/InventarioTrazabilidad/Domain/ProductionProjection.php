<?php

namespace Simon\InventarioTrazabilidad\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class ProductionProjection
{
    public int $balance;

    public int $injectionNeeded;

    public function __construct(public int $available, public int $inProcess, public int $pending)
    {
        if ($available < 0 || $inProcess < 0 || $pending < 0) {
            throw new BusinessRuleViolation('Las fuentes de la proyección deben informar cantidades de pares no negativas.');
        }

        $this->balance = $available + $inProcess - $pending;
        $this->injectionNeeded = max(0, -$this->balance);
    }
}
