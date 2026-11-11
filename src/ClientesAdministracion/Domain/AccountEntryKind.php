<?php

namespace Simon\ClientesAdministracion\Domain;

enum AccountEntryKind: string
{
    case Opening = 'opening';
    case Charge = 'charge';
    case Payment = 'payment';
    case Adjustment = 'adjustment';
    case Reconciliation = 'reconciliation';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Saldo inicial', self::Charge => 'Cargo', self::Payment => 'Cobro',
            self::Adjustment => 'Ajuste', self::Reconciliation => 'Conciliación',
        };
    }
}
