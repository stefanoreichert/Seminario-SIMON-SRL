<?php

namespace Simon\VentasPedidos\Application;

use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Domain\OrderOrigin;

final readonly class RegisterOrder
{
    public function __construct(private AccessPolicy $access, private OrderRegistration $orders) {}

    /** @param list<array{variant_id:int, quantity:int}> $lines */
    public function handle(int $actorId, string $operationId, int $customerId, string $origin, array $lines, ?int $deliveryAddressId = null): int
    {
        $permission = $this->access->allows($actorId, 'orders.manage') ? 'orders.manage' : 'orders.create';
        $actor = $this->access->require($actorId, $permission);
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $operationId)) {
            throw new BusinessRuleViolation('La identificación de la operación no es válida.');
        }

        $orderOrigin = OrderOrigin::parse($origin);

        if ($permission === 'orders.create' && ($actor->salesIdentifier === null || $actor->salesIdentifier !== $orderOrigin->value)) {
            throw new BusinessRuleViolation('El origen debe coincidir con el identificador del vendedor.');
        }

        if ($lines === []) {
            throw new BusinessRuleViolation('Seleccioná un origen y al menos un artículo.');
        }

        if ($deliveryAddressId !== null && $deliveryAddressId < 1) {
            throw new BusinessRuleViolation('La dirección de entrega no es válida.');
        }

        $seen = [];

        foreach ($lines as $line) {
            if ($line['quantity'] < 1) {
                throw new BusinessRuleViolation('Las cantidades deben ser mayores que cero.');
            }

            if ($line['variant_id'] < 1 || in_array($line['variant_id'], $seen, true)) {
                throw new BusinessRuleViolation('Combiná las cantidades de las líneas del mismo artículo.');
            }

            $seen[] = $line['variant_id'];
        }

        return $this->orders->register($operationId, $customerId, $actor->id, $orderOrigin->value, $lines, $deliveryAddressId);
    }
}
