<?php

namespace Simon\VentasPedidos\Application;

use Simon\Shared\Application\ReadSourceUnavailable;

interface PendingDemand
{
    /** @return array{totals:array{pairs:int,orders:int,articles:int},articles:array{items:list<array<string,mixed>>,page:int,pages:int,total:int}} */
    public function forProduction(int $actorId, int $page = 1): array;

    /**
     * @param  list<int>  $variantIds
     * @return array<int,int> Cantidad pendiente de cada ID solicitado, incluido cero.
     *
     * @throws ReadSourceUnavailable
     */
    public function quantities(int $actorId, array $variantIds): array;
}
