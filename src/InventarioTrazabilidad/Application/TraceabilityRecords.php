<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\InventarioTrazabilidad\Domain\TraceabilityFilter;

interface TraceabilityRecords
{
    /** @return array{items:list<array{id:int,sequence:int,occurred_at:string,actor_name:string,kind:string,quantity:int,variant_id:int,lot:string,manufactured_on:string,order_line_id:?int,shipment:?array,delivery_destination:?array}>,total:int,page:int,pages:int} */
    public function search(TraceabilityFilter $filter, int $page): array;
}
