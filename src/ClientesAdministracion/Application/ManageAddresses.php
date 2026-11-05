<?php

namespace Simon\ClientesAdministracion\Application;

use Simon\ClientesAdministracion\Domain\DeliveryAddress;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;

final readonly class ManageAddresses
{
    public function __construct(private AccessPolicy $access, private AddressRecords $addresses) {}

    public function save(int $actorId, int $customerId, ?int $addressId, string $label, string $street, string $locality, string $province, ?string $postalCode = null, ?string $country = null, ?string $references = null, bool $active = true): int
    {
        $this->access->require($actorId, 'clients.manage');

        if ($customerId < 1 || ($addressId !== null && $addressId < 1)) {
            throw new BusinessRuleViolation('El cliente o la dirección no son válidos.');
        }

        return $this->addresses->save($customerId, $addressId, new DeliveryAddress($label, $street, $locality, $province, $postalCode, $country, $references), $active);
    }

    /** @return list<array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string,is_active:bool}> */
    public function all(int $actorId, int $customerId): array
    {
        $this->access->require($actorId, 'clients.manage');

        return $this->addresses->forCustomer($customerId, true);
    }

    /** @return array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string,is_active:bool}|null */
    public function find(int $actorId, int $customerId, int $addressId): ?array
    {
        foreach ($this->all($actorId, $customerId) as $address) {
            if ($address['id'] === $addressId) {
                return $address;
            }
        }

        return null;
    }

    /** @return list<array{id:int,customer_id:int,label:string,street:string,locality:string,province:string,postal_code:?string,country:?string,references:?string,is_active:bool}> */
    public function options(int $actorId, int $customerId): array
    {
        $permission = 'clients.manage';

        foreach (['clients.manage', 'orders.create', 'orders.manage', 'inventory.dispatch'] as $candidate) {
            if ($this->access->allows($actorId, $candidate)) {
                $permission = $candidate;
                break;
            }
        }

        $this->access->require($actorId, $permission);

        return $this->addresses->forCustomer($customerId);
    }
}
