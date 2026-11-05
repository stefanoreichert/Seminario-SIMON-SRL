<?php

namespace Simon\ClientesAdministracion\Infrastructure;

use Illuminate\Support\Facades\DB;
use Simon\ClientesAdministracion\Application\AddressRecords;
use Simon\ClientesAdministracion\Domain\DeliveryAddress;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlDeliveryAddresses implements AddressRecords
{
    public function save(int $customerId, ?int $addressId, DeliveryAddress $address, bool $active): int
    {
        return DB::transaction(function () use ($customerId, $addressId, $address, $active): int {
            if (DB::table('business_customers')->where('id', $customerId)->lockForUpdate()->first(['id']) === null) {
                throw new BusinessRuleViolation('El cliente no existe.');
            }

            $values = [...$address->toArray(), 'is_active' => $active, 'updated_at' => now()];

            if ($addressId === null) {
                return DB::table('customer_delivery_addresses')->insertGetId(['customer_id' => $customerId, ...$values, 'created_at' => now()]);
            }

            $existing = DB::table('customer_delivery_addresses')->where('customer_id', $customerId)->where('id', $addressId)->lockForUpdate()->first(['id']);

            if ($existing === null) {
                throw new BusinessRuleViolation('La dirección no pertenece a este cliente o no existe.');
            }

            DB::table('customer_delivery_addresses')->where('id', $addressId)->update($values);

            return $addressId;
        }, 5);
    }

    public function forCustomer(int $customerId, bool $includeInactive = false): array
    {
        return DB::table('customer_delivery_addresses')->where('customer_id', $customerId)
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->select('id', 'customer_id', 'label', 'street', 'locality', 'province', 'postal_code', 'country', 'references', 'is_active')
            ->orderBy('label')->orderBy('id')->get()->map(fn (object $address): array => [...(array) $address, 'is_active' => (bool) $address->is_active])->all();
    }

    public function snapshot(int $customerId, int $addressId): array
    {
        $address = DB::table('customer_delivery_addresses')->where('customer_id', $customerId)->where('id', $addressId)
            ->where('is_active', true)->lockForUpdate()
            ->first(['id', 'customer_id', 'label', 'street', 'locality', 'province', 'postal_code', 'country', 'references']);

        if ($address === null) {
            throw new BusinessRuleViolation('Seleccioná una dirección activa perteneciente al cliente del pedido.');
        }

        return (array) $address;
    }
}
