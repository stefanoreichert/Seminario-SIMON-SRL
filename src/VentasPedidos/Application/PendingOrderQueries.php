<?php

namespace Simon\VentasPedidos\Application;

interface PendingOrderQueries
{
    /**
     * @return array{totals:array{pairs:int,orders:int,articles:int},customers:array{items:list<array{customer_id:int,variant_id:int,pairs:int,orders:int}>,page:int,pages:int,total:int},articles:array{items:list<array{variant_id:int,pairs:int,orders:int}>,page:int,pages:int,total:int}}
     */
    public function snapshot(?int $sellerId, ?string $origin, int $customerPage, int $articlePage, bool $includeCustomers): array;
}
