<?php

namespace Simon\ClientesAdministracion\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

final class CurrentAccount
{
    public function __construct(public readonly int $customerId, private Money $balance, private bool $initialized)
    {
        if ($customerId < 1) {
            throw new BusinessRuleViolation('La cuenta debe pertenecer a un cliente válido.');
        }
    }

    public function post(AccountEntryKind $kind, Money $amount): Money
    {
        if ($amount->currency !== $this->balance->currency || $amount->scale !== $this->balance->scale) {
            throw new BusinessRuleViolation('El movimiento debe usar la misma moneda que la cuenta.');
        }

        if ($kind === AccountEntryKind::Opening) {
            if ($this->initialized) {
                throw new BusinessRuleViolation('La cuenta ya tiene saldo inicial. Registrá un ajuste con su fuente si corresponde.');
            }

            $this->initialized = true;
        } elseif (! $this->initialized) {
            throw new BusinessRuleViolation('Registrá el saldo inicial y su fuente antes de cargar otros movimientos.');
        }

        if (in_array($kind, [AccountEntryKind::Charge, AccountEntryKind::Payment], true) && $amount->minor <= 0) {
            throw new BusinessRuleViolation('Cargos y cobros requieren un importe mayor que cero.');
        }

        if ($kind === AccountEntryKind::Adjustment && $amount->minor === 0) {
            throw new BusinessRuleViolation('Un ajuste debe modificar el saldo e indicar su motivo.');
        }

        if ($kind === AccountEntryKind::Reconciliation) {
            if ($amount->minor !== $this->balance->minor) {
                throw new BusinessRuleViolation('El saldo informado no coincide con la cuenta. Revisá la fuente y registrá un ajuste documentado si corresponde.');
            }

            return new Money(0, $amount->currency, $amount->scale);
        }

        $delta = new Money($kind === AccountEntryKind::Payment ? -$amount->minor : $amount->minor, $amount->currency, $amount->scale);
        $this->balance = $this->balance->add($delta);

        return $delta;
    }

    public function balance(): Money
    {
        return $this->balance;
    }
}
