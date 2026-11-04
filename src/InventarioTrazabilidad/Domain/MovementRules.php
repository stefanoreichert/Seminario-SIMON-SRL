<?php

namespace Simon\InventarioTrazabilidad\Domain;

use DateTimeImmutable;
use Simon\Shared\Domain\BusinessRuleViolation;

final class MovementRules
{
    public static function quantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new BusinessRuleViolation('La cantidad debe ser un número entero mayor que cero.');
        }
    }

    public static function batch(string $lot, string $manufacturedOn): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $manufacturedOn);

        if (trim($lot) === '' || strlen($lot) > 100 || $date === false || $date->format('Y-m-d') !== $manufacturedOn || $date > new DateTimeImmutable('today')) {
            throw new BusinessRuleViolation('Ingresá un lote y una fecha de fabricación válida, no futura.');
        }
    }

    public static function operationId(string $operationId): void
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $operationId)) {
            throw new BusinessRuleViolation('La identificación de la operación no es válida.');
        }
    }
}
