<?php

namespace Simon\InventarioTrazabilidad\Application;

interface DispatchAuthorization
{
    /** @return array{order_id:int, authorized_by:int, billing_validated:bool|int, credit_validated:bool|int, conditions:string, updated_at:string} */
    public function assertAuthorized(int $orderId): array;
}
