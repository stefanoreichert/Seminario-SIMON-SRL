<?php

namespace Simon\InventarioTrazabilidad\Application;

interface CatalogVariants
{
    /** @return list<object{id:int,code:string,model:string,color:string,size:string}> */
    public function options(): array;

    /**
     * @param  list<int>  $variantIds
     * @return list<int>
     */
    public function missing(array $variantIds): array;
}
