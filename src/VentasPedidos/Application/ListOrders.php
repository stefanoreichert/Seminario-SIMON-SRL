<?php

namespace Simon\VentasPedidos\Application;

use Simon\Shared\Application\AccessPolicy;
use Simon\VentasPedidos\Domain\OrderOrigin;

final readonly class ListOrders
{
    public function __construct(private AccessPolicy $access, private OrderQueries $orders) {}

    /**
     * @return array{orders:list<object{id:int,number:?string,customer_id:int,seller_id:int,origin:string,status:string,delivery_destination:?array}>,lines:list<object{id:int,order_id:int,variant_id:int,quantity:int,dispatched:int,customer_id:int,delivery_destination:?array}>}
     */
    public function handle(int $actorId, ?string $origin = null): array
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
        $filter = $origin === null ? null : OrderOrigin::parse($origin);
        $orders = $this->orders->orders($allOrders ? null : $actor->id, $filter?->value);

        return [
            'orders' => $orders,
            'lines' => $this->orders->lines(array_map(fn (object $order): int => $order->id, $orders)),
        ];
    }
}
