<?php

namespace Simon\VentasPedidos\Application;

interface DispatchableOrders
{
    public function dispatchable(int $orderId): bool;
}
