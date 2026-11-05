<?php

namespace Simon\ClientesAdministracion\Application;

interface DeliveryAddresses
{
    /** @return list<array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string,is_active:bool}> */
    public function forCustomer(int $customerId, bool $includeInactive = false): array;

    /** @return array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string} */
    public function snapshot(int $customerId, int $addressId): array;
}
