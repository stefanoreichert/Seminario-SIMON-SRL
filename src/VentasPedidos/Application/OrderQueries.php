<?php

namespace Simon\VentasPedidos\Application;

interface OrderQueries
{
    /** @return list<object{id:int,number:?string,customer_id:int,seller_id:int,origin:string,status:string,delivery_destination:?array}> */
    public function orders(?int $sellerId = null, ?string $origin = null): array;

    /**
     * @param  list<int>  $orderIds
     * @return list<object{id:int,order_id:int,variant_id:int,quantity:int,dispatched:int,pending:int,status:string,customer_id:int,delivery_destination:?array}>
     */
    public function lines(array $orderIds): array;
}
