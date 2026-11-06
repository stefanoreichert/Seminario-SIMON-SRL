<?php

namespace Simon\InventarioTrazabilidad\Domain;

use DateTimeImmutable;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class TraceabilityFilter
{
    public ?string $lot;

    public function __construct(?string $lot = null, public ?string $manufacturedFrom = null, public ?string $manufacturedTo = null)
    {
        $this->lot = $lot === null || trim($lot) === '' ? null : trim($lot);

        if ($this->lot !== null && mb_strlen($this->lot) > 100) {
            throw new BusinessRuleViolation('El lote admite hasta 100 caracteres.');
        }

        foreach ([$manufacturedFrom, $manufacturedTo] as $value) {
            if ($value !== null) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

                if ($date === false || $date->format('Y-m-d') !== $value) {
                    throw new BusinessRuleViolation('La fecha de fabricación no es válida.');
                }
            }
        }

        if ($manufacturedFrom !== null && $manufacturedTo !== null && $manufacturedFrom > $manufacturedTo) {
            throw new BusinessRuleViolation('La fecha inicial no puede ser posterior a la final.');
        }
    }

    public function isEmpty(): bool
    {
        return $this->lot === null && $this->manufacturedFrom === null && $this->manufacturedTo === null;
    }
}
