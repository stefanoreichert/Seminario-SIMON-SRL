<?php

namespace Simon\InventarioTrazabilidad\Application;

interface CatalogVariants
{
    /**
     * @param  list<int>  $variantIds
     * @return list<int>
     */
    public function missing(array $variantIds): array;
}
