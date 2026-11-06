<?php

namespace Simon\InventarioTrazabilidad\Application;

use Simon\ClientesAdministracion\Application\CustomerDirectory;
use Simon\InventarioTrazabilidad\Domain\TraceabilityFilter;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\DispatchedOrderReferences;

final readonly class SearchTraceability
{
    public function __construct(private AccessPolicy $access, private TraceabilityRecords $records, private DispatchedOrderReferences $orders, private CustomerDirectory $customers, private CatalogVariants $catalog) {}

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function handle(int $actorId, ?string $lot = null, ?string $manufacturedFrom = null, ?string $manufacturedTo = null, int $page = 1): array
    {
        $permission = 'inventory.view';

        foreach (['inventory.view', 'inventory.dispatch', 'audit.view', 'orders.manage', 'dispatch.authorize'] as $candidate) {
            if ($this->access->allows($actorId, $candidate)) {
                $permission = $candidate;
                break;
            }
        }

        $this->access->require($actorId, $permission);
        $filter = new TraceabilityFilter($lot, $manufacturedFrom, $manufacturedTo);

        if ($page < 1) {
            throw new BusinessRuleViolation('La página del historial no es válida.');
        }

        if ($filter->isEmpty()) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }

        $result = $this->records->search($filter, $page);
        $legacy = array_values(array_filter($result['items'], fn (array $row): bool => $row['shipment'] === null));
        $lines = array_values(array_unique(array_filter(array_column($legacy, 'order_line_id'), fn (?int $id): bool => $id !== null)));
        $orders = array_column($this->orders->forLines($lines), null, 'line_id');
        $customers = array_column($this->customers->identified(array_values(array_unique(array_column($orders, 'customer_id')))), null, 'id');
        $articles = array_column($this->catalog->identified(array_values(array_unique(array_column($legacy, 'variant_id')))), null, 'id');

        $result['items'] = array_map(function (array $row) use ($orders, $customers, $articles): array {
            $stored = $row['shipment'] !== null;
            $order = $stored ? $row['shipment']['order'] : ($orders[$row['order_line_id']] ?? null);

            return [
                ...$row, 'stored_identity' => $stored, 'order' => $order,
                'customer' => $stored ? $row['shipment']['customer'] : ($customers[$order['customer_id'] ?? null] ?? null),
                'article' => $stored ? $row['shipment']['article'] : ($articles[$row['variant_id']] ?? null),
            ];
        }, $result['items']);

        return $result;
    }
}
