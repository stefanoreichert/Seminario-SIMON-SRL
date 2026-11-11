<?php

namespace Simon\ClientesAdministracion\Application;

use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\OrderQueries;

final readonly class QueryDispatchAuthorizations
{
    public function __construct(
        private AccessPolicy $access,
        private OrderQueries $orders,
        private CustomerDirectory $customers,
        private CustomerRecords $records,
        private CatalogVariants $catalog,
    ) {}

    /** @return array{orders:array<string,mixed>,selected:?object,lines:list<object>} */
    public function handle(int $actorId, string $term, int $page, ?int $selectedId): array
    {
        $this->access->require($actorId, 'dispatch.authorize');
        if ($page < 1 || mb_strlen($term) > 255 || ($selectedId !== null && $selectedId < 1)) {
            throw new BusinessRuleViolation('La búsqueda o el pedido solicitado no son válidos.');
        }
        $term = trim($term);
        $matchingCustomers = $term === '' ? [] : $this->customers->matchingName($term);
        $report = $this->orders->authorizationPage($term, $matchingCustomers, $page);
        $selected = $selectedId === null ? null : $this->orders->authorizationOrder($selectedId);
        $items = $report['items'];
        $toDescribe = $selected === null ? $items : [...$items, $selected];
        $customerIds = array_values(array_unique(array_map(fn (object $item): int => (int) $item->customer_id, $toDescribe)));
        $orderIds = array_values(array_unique(array_map(fn (object $item): int => (int) $item->id, $toDescribe)));
        $customers = array_column($this->customers->identified($customerIds), null, 'id');
        $authorizations = array_column($this->records->authorizations($orderIds), null, 'order_id');
        foreach ($toDescribe as $item) {
            $authorization = $authorizations[$item->id] ?? [];
            $item->customer_name = $customers[$item->customer_id]['name'] ?? 'Cliente no disponible';
            $item->billing_validated = $authorization['billing_validated'] ?? false;
            $item->credit_validated = $authorization['credit_validated'] ?? false;
            $item->conditions = $authorization['conditions'] ?? '';
            $item->validated_at = $authorization['updated_at'] ?? null;
            $item->validator_id = $authorization['authorized_by'] ?? null;
        }

        $lines = $selected === null ? [] : $this->orders->lines([$selected->id]);
        $variantIds = array_values(array_unique(array_map(fn (object $line): int => (int) $line->variant_id, $lines)));
        $variants = array_column($this->catalog->identified($variantIds), null, 'id');
        foreach ($lines as $line) {
            $variant = $variants[$line->variant_id] ?? null;
            $line->article = $variant === null ? 'Artículo no disponible' : $variant['code'].' · '.$variant['model'].' / '.$variant['color'].' / '.$variant['size'];
        }

        return ['orders' => $report, 'selected' => $selected, 'lines' => $lines];
    }
}
