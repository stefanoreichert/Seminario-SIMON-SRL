<?php

namespace Simon\InventarioTrazabilidad\Domain;

use DateTimeImmutable;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class SupplyExpiry
{
    public function __construct(public string $date)
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new BusinessRuleViolation('La fecha de vencimiento no es válida.');
        }
    }

    public function warning(string $today, int $anticipationDays): string
    {
        if ($anticipationDays < 0 || $anticipationDays > 3650) {
            throw new BusinessRuleViolation('La anticipación debe estar entre 0 y 3650 días.');
        }

        $now = new DateTimeImmutable($today);
        $expiry = new DateTimeImmutable($this->date);

        if ($expiry < $now) {
            return 'Vencido';
        }

        if ($expiry == $now) {
            return 'Vence hoy';
        }

        return $expiry <= $now->modify('+'.$anticipationDays.' days') ? 'Próximo a vencer' : 'Vigente';
    }
}
