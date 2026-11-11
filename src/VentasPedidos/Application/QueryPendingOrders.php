<?php

namespace Simon\VentasPedidos\Application;

use Simon\ClientesAdministracion\Application\CustomerDirectory;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Domain\OrderOrigin;

final readonly class QueryPendingOrders implements PendingDemand
{
    public function __construct(private AccessPolicy $access, private PendingOrderQueries $orders, private CustomerDirectory $customers, private CatalogVariants $catalog) {}

    /** @return array{totals:array{pairs:int,orders:int,articles:int},customers:array{items:list<array<string,mixed>>,page:int,pages:int,total:int},articles:array{items:list<array<string,mixed>>,page:int,pages:int,total:int}} */
    public function handle(int $actorId, ?string $origin = null, int $customerPage = 1, int $articlePage = 1): array
    {
        $permission = 'orders.create';
        $allOrders = false;

        foreach (['orders.manage', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize'] as $candidate) {
            if ($this->access->allows($actorId, $candidate)) {
                $permission = $candidate;
                $allOrders = true;
                break;
            }
        }

        $actor = $this->access->require($actorId, $permission);
        $filter = $origin === null ? null : OrderOrigin::parse($origin)->value;
        $this->validatePage($customerPage);
        $this->validatePage($articlePage);

        return $this->describe($this->orders->snapshot($allOrders ? null : $actor->id, $filter, $customerPage, $articlePage, true));
    }

    public function forProduction(int $actorId, int $page = 1): array
    {
        $permission = $this->access->allows($actorId, 'production.manage') ? 'production.manage' : 'inventory.view';
        $this->access->require($actorId, $permission);
        $this->validatePage($page);
        $report = $this->describe($this->orders->snapshot(null, null, 1, $page, false));

        return ['totals' => $report['totals'], 'articles' => $report['articles']];
    }

    public function quantities(int $actorId, array $variantIds): array
    {
        $permission = $this->access->allows($actorId, 'production.manage') ? 'production.manage' : 'inventory.view';
        $this->access->require($actorId, $permission);

        return $this->orders->quantities($variantIds);
    }

    private function validatePage(int $page): void
    {
        if ($page < 1) {
            throw new BusinessRuleViolation('La página de pendientes no es válida.');
        }
    }

    /**
     * @param  array<string,mixed>  $report
     * @return array<string,mixed>
     */
    private function describe(array $report): array
    {
        $customerIds = array_values(array_unique(array_column($report['customers']['items'], 'customer_id')));
        $variantIds = array_values(array_unique([...array_column($report['customers']['items'], 'variant_id'), ...array_column($report['articles']['items'], 'variant_id')]));
        $customers = array_column($this->customers->identified($customerIds), null, 'id');
        $variants = array_column($this->catalog->identified($variantIds), null, 'id');
        $report['customers']['items'] = array_map(fn (array $row): array => [...$row, 'customer' => $customers[$row['customer_id']] ?? null, 'article' => $variants[$row['variant_id']] ?? null], $report['customers']['items']);
        $report['articles']['items'] = array_map(fn (array $row): array => [...$row, 'article' => $variants[$row['variant_id']] ?? null], $report['articles']['items']);

        return $report;
    }
}
