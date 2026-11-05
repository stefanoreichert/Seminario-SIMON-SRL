<?php

namespace Simon\VentasPedidos\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

enum OrderOrigin: string
{
    case Sandra = 'S';
    case Gustavo = 'G';
    case Factory = 'F';

    public function label(): string
    {
        return match ($this) {
            self::Sandra => 'S · Sandra',
            self::Gustavo => 'G · Gustavo',
            self::Factory => 'F · Fábrica',
        };
    }

    public static function parse(string $value): self
    {
        return self::tryFrom($value) ?? throw new BusinessRuleViolation('El origen debe ser S, G o F.');
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $origin) {
            $labels[$origin->value] = $origin->label();
        }

        return $labels;
    }
}
