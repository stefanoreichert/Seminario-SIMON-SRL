<?php

namespace Simon\InventarioTrazabilidad\Application;

interface OrderFulfillment
{
    /** @return array{id:int, order_id:int, variant_id:int, quantity:int, dispatched:int, customer_id:int, delivery_destination:?array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string}} */
    public function line(int $lineId): array;

    public function dispatch(int $lineId, int $quantity): void;
}
