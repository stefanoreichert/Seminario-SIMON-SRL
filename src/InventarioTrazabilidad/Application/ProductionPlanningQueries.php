<?php

namespace Simon\InventarioTrazabilidad\Application;

use Closure;
use Simon\Shared\Application\ReadSourceUnavailable;

interface ProductionPlanningQueries
{
    /**
     * @param  Closure(): array<string, mixed>  $operation
     * @return array<string, mixed>
     *
     * @throws ReadSourceUnavailable
     */
    public function snapshot(Closure $operation): array;

    /** @return array{items:list<array{id:int,code:string,model:string,color:string,size:string,available:int,in_process:int}>,page:int,pages:int,total:int} */
    public function page(int $page): array;
}
