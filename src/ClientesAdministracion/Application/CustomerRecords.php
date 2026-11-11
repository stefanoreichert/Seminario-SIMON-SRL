<?php

namespace Simon\ClientesAdministracion\Application;

use Simon\ClientesAdministracion\Domain\CustomerProfile;

interface CustomerRecords
{
    public function register(CustomerProfile $profile, string $creditHistory, int $actorId): int;

    public function update(int $customerId, CustomerProfile $profile): void;

    /**
     * @return array{items: list<array{id: int, name: string, cuit: string, phone: ?string, email: ?string}>, total: int, page: int, per_page: int}
     */
    public function search(string $query, int $page): array;

    /**
     * @return array{id: int, name: string, cuit: string, phone: ?string, email: ?string, credit_history: string, credit_entries: list<array{id: int, actor_id: int, actor_name: string, note: string, recorded_at: string}>}|null
     */
    public function find(int $customerId): ?array;

    public function updateCredit(int $customerId, string $creditHistory, int $actorId): void;

    public function authorize(int $orderId, int $actorId, bool $billing, bool $credit, string $conditions): void;

    /** @param list<int> $orderIds
     * @return list<array<string,mixed>>
     */
    public function authorizations(array $orderIds): array;
}
