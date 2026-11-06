<?php

namespace Simon\VentasPedidos\Application;

use Simon\ClientesAdministracion\Application\DeliveryAddresses;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\Actor;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Domain\OrderLines;
use Simon\VentasPedidos\Domain\OrderOrigin;
use Simon\VentasPedidos\Domain\OrderStatus;

final readonly class ManageOrders
{
    public function __construct(
        private AccessPolicy $access,
        private UnitOfWork $transactions,
        private OrderWorkflow $orders,
        private CatalogVariants $catalog,
        private DeliveryAddresses $addresses,
    ) {}

    /** @return array<string,mixed> */
    public function view(int $actorId, int $orderId): array
    {
        $permission = 'orders.create';
        $allOrders = false;

        foreach (['orders.manage', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize'] as $candidate) {
            if ($this->access->allows($actorId, $candidate)) {
                $permission = $candidate;
                $allOrders = true;
                break;
            }
        }

        $actor = $this->access->require($actorId, $permission);
        $order = $this->find($orderId);

        if (! $allOrders && $order['seller_id'] !== $actor->id) {
            throw new PermissionDenied('No tenés permiso para consultar este pedido.');
        }

        return $order;
    }

    /** @param list<array{variant_id:int,quantity:int}> $lines */
    public function saveDraft(int $actorId, string $operationId, int $orderId, int $revision, int $customerId, string $origin, array $lines, ?int $deliveryAddressId = null): void
    {
        $this->validateOperation($operationId, $revision);
        $items = (new OrderLines($lines))->items;
        $orderOrigin = OrderOrigin::parse($origin);
        $request = ['action' => 'edit', 'order_id' => $orderId, 'revision' => $revision, 'customer_id' => $customerId, 'origin' => $origin, 'lines' => $items, 'delivery_address_id' => $deliveryAddressId];

        $this->transactions->run(function () use ($actorId, $operationId, $orderId, $revision, $customerId, $orderOrigin, $items, $deliveryAddressId, $request): void {
            [$actor, $order] = $this->writable($actorId, $orderId);

            if ($this->orders->replay($operationId, $actor->id, $request)) {
                return;
            }

            $this->assertRevision($order, $revision);
            OrderStatus::from($order['status'])->assertDraft(array_sum(array_column($order['lines'], 'dispatched')));

            if (! $this->access->allows($actorId, 'orders.manage') && $actor->salesIdentifier !== $orderOrigin->value) {
                throw new BusinessRuleViolation('El origen debe coincidir con el identificador del vendedor.');
            }

            if ($deliveryAddressId !== null && $deliveryAddressId < 1) {
                throw new BusinessRuleViolation('La dirección de entrega no es válida.');
            }

            if ($this->catalog->missing(array_column($items, 'variant_id')) !== []) {
                throw new BusinessRuleViolation('Uno de los artículos ya no existe en el catálogo.');
            }

            $this->orders->assertNoDuplicate($customerId, $items, $orderId);
            $destination = $deliveryAddressId === null ? null : $this->addresses->snapshot($customerId, $deliveryAddressId);
            $this->orders->saveDraft($orderId, $revision, $customerId, $orderOrigin->value, $items, $deliveryAddressId, $destination);
            $this->orders->record($operationId, $actorId, $request, OrderStatus::Draft, OrderStatus::Draft);
        });
    }

    public function transition(int $actorId, string $operationId, int $orderId, int $revision, string $action, ?string $reason = null): void
    {
        $this->validateOperation($operationId, $revision);

        if (! in_array($action, ['confirm', 'cancel'], true)) {
            throw new BusinessRuleViolation('La acción del pedido no es válida.');
        }

        $reason = $reason === null ? null : trim($reason);
        $request = ['action' => $action, 'order_id' => $orderId, 'revision' => $revision, 'reason' => $reason];

        $this->transactions->run(function () use ($actorId, $operationId, $orderId, $revision, $action, $reason, $request): void {
            [$actor, $order] = $this->writable($actorId, $orderId);

            if ($this->orders->replay($operationId, $actor->id, $request)) {
                return;
            }

            $this->assertRevision($order, $revision);
            $from = OrderStatus::from($order['status']);
            $destination = null;

            if ($action === 'confirm') {
                $from->assertDraft(array_sum(array_column($order['lines'], 'dispatched')));
                $items = array_map(fn (array $line): array => ['variant_id' => $line['variant_id'], 'quantity' => $line['quantity']], $order['lines']);
                $this->orders->assertNoDuplicate($order['customer_id'], $items, $orderId);
                $destination = $order['delivery_address_id'] === null ? null : $this->addresses->snapshot($order['customer_id'], $order['delivery_address_id']);
                $to = OrderStatus::Confirmed;
            } else {
                $from->assertCancellation($reason ?? '');
                $to = OrderStatus::Cancelled;
            }

            $this->orders->setStatus($orderId, $revision, $to, $destination);
            $this->orders->record($operationId, $actorId, $request, $from, $to, $reason);
        });
    }

    /** @return array<string,mixed> */
    private function find(int $orderId): array
    {
        return $this->orders->find($orderId) ?? throw new BusinessRuleViolation('El pedido no existe.');
    }

    /** @return array{Actor,array<string,mixed>} */
    private function writable(int $actorId, int $orderId): array
    {
        $manage = $this->access->allows($actorId, 'orders.manage');
        $actor = $this->access->require($actorId, $manage ? 'orders.manage' : 'orders.create');
        $order = $this->find($orderId);

        if (! $manage && $order['seller_id'] !== $actor->id) {
            throw new PermissionDenied('Solo podés modificar tus propios pedidos.');
        }

        return [$actor, $order];
    }

    private function validateOperation(string $operationId, int $revision): void
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $operationId) || $revision < 0) {
            throw new BusinessRuleViolation('La identificación o versión del cambio no es válida.');
        }
    }

    /** @param array<string,mixed> $order */
    private function assertRevision(array $order, int $revision): void
    {
        if ($order['revision'] !== $revision) {
            throw new BusinessRuleViolation('El pedido cambió desde que abriste la pantalla. Recargalo antes de continuar.');
        }
    }
}
