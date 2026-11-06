<?php

namespace Simon\VentasPedidos\Application;

interface DispatchedOrderReferences
{
    /**
     * @param  list<int>  $lineIds
     * @return list<array{line_id:int,id:int,number:string,origin:string,customer_id:int}>
     */
    public function forLines(array $lineIds): array;
}
