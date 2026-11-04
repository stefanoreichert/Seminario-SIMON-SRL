<?php

namespace Simon\InventarioTrazabilidad\Application;

interface OrderFulfillment
{
    /** @return array{id:int, order_id:int, variant_id:int, quantity:int, dispatched:int} */
    public function line(int $lineId): array;

    public function dispatch(int $lineId, int $quantity): void;
}
