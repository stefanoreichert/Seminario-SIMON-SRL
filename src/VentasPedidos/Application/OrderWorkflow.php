<?php

namespace Simon\VentasPedidos\Application;

use Simon\VentasPedidos\Domain\OrderStatus;

interface OrderWorkflow
{
    /** @return array{id:int,number:string,customer_id:int,seller_id:int,origin:string,status:string,revision:int,delivery_address_id:?int,delivery_destination:?array,lines:list<array{id:int,variant_id:int,quantity:int,dispatched:int}>,changes:list<array{action:string,actor_name:string,from_status:string,to_status:string,reason:?string,recorded_at:string}>}|null */
    public function find(int $orderId): ?array;

    /** @param list<array{variant_id:int,quantity:int}> $lines */
    public function assertNoDuplicate(int $customerId, array $lines, ?int $exceptOrderId = null): void;

    /** @param list<array{variant_id:int,quantity:int}> $lines */
    public function saveDraft(int $orderId, int $revision, int $customerId, string $origin, array $lines, ?int $deliveryAddressId, ?array $destination): void;

    public function setStatus(int $orderId, int $revision, OrderStatus $status, ?array $destination = null): void;

    /** @param array<string,mixed> $request */
    public function replay(string $operationId, int $actorId, array $request): bool;

    /** @param array<string,mixed> $request */
    public function record(string $operationId, int $actorId, array $request, OrderStatus $from, OrderStatus $to, ?string $reason = null): void;
}
