<?php

namespace Simon\VentasPedidos\Application;

interface OrderRegistration
{
    /** @param list<array{variant_id:int, quantity:int}> $lines */
    public function register(string $operationId, int $customerId, int $sellerId, string $origin, array $lines, ?int $deliveryAddressId = null): int;
}
