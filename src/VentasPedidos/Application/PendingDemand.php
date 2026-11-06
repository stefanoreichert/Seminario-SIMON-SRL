<?php

namespace Simon\VentasPedidos\Application;

interface PendingDemand
{
    /** @return array{totals:array{pairs:int,orders:int,articles:int},articles:array{items:list<array<string,mixed>>,page:int,pages:int,total:int}} */
    public function forProduction(int $actorId, int $page = 1): array;
}
