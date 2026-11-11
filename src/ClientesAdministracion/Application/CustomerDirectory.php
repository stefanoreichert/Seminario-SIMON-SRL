<?php

namespace Simon\ClientesAdministracion\Application;

interface CustomerDirectory
{
    /** @return list<object{id:int,name:string,cuit:string}> */
    public function customers(): array;

    /**
     * @param  list<int>  $ids
     * @return list<array{id:int,name:string,cuit:string}>
     */
    public function identified(array $ids): array;

    /** @return list<int> */
    public function matchingName(string $term): array;
}
