<?php

namespace Simon\ClientesAdministracion\Application;

interface CustomerDirectory
{
    /** @return list<object{id:int,name:string,cuit:string}> */
    public function customers(): array;
}
