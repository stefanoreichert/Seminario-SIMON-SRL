<?php

namespace Simon\ClientesAdministracion\Application;

use Simon\ClientesAdministracion\Domain\DeliveryAddress;

interface AddressRecords extends DeliveryAddresses
{
    public function save(int $customerId, ?int $addressId, DeliveryAddress $address, bool $active): int;
}
