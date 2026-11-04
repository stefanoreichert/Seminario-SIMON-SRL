<?php

namespace Simon\VentasPedidos\Application;

use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class RegisterOrder
{
    public function __construct(private AccessPolicy $access, private OrderRegistration $orders) {}

    /** @param list<array{variant_id:int, quantity:int}> $lines */
    public function handle(int $actorId, string $operationId, int $customerId, string $origin, array $lines): int
    {
        $permission = $this->access->allows($actorId, 'orders.manage') ? 'orders.manage' : 'orders.create';
        $actor = $this->access->require($actorId, $permission);
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $operationId)) {
            throw new BusinessRuleViolation('La identificación de la operación no es válida.');
        }

        if ($permission === 'orders.create' && ($actor->salesIdentifier === null || $actor->salesIdentifier !== $origin)) {
            throw new BusinessRuleViolation('El origen debe coincidir con el identificador del vendedor.');
        }

        if (! in_array($origin, ['S', 'G', 'F'], true) || $lines === []) {
            throw new BusinessRuleViolation('Seleccioná un origen y al menos un artículo.');
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

        return $this->orders->register($operationId, $customerId, $actor->id, $origin, $lines);
    }
}
