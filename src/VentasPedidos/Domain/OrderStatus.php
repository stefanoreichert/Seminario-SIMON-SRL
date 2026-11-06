<?php

namespace Simon\VentasPedidos\Domain;

use Simon\Shared\Domain\BusinessRuleViolation;

enum OrderStatus: string
{
    case Draft = 'Borrador';
    case Confirmed = 'Confirmado';
    case Partial = 'Parcial';
    case Delivered = 'Entregado';
    case Cancelled = 'Anulado';

    public function acceptsDispatch(): bool
    {
        return in_array($this, [self::Confirmed, self::Partial], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed, self::Partial], true);
    }

    public function assertCancellation(string $reason): void
    {
        if (! $this->isActive()) {
            throw new BusinessRuleViolation('Solo se pueden anular borradores o el saldo de pedidos confirmados y parciales.');
        }

        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw new BusinessRuleViolation('Indicá un motivo de anulación de hasta 1000 caracteres.');
        }
    }

    public function assertDraft(int $dispatched): void
    {
        if ($this !== self::Draft || $dispatched !== 0) {
            throw new BusinessRuleViolation('Solo se pueden editar o confirmar borradores sin entregas.');
        }
    }

    public function afterDispatch(bool $hasPending): self
    {
        if (! $this->acceptsDispatch()) {
            throw new BusinessRuleViolation('El pedido no admite despachos en su estado actual.');
        }

        return $hasPending ? self::Partial : self::Delivered;
    }
}
