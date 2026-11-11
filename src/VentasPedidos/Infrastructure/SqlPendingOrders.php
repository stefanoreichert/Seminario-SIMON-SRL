<?php

namespace Simon\VentasPedidos\Infrastructure;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Simon\Shared\Application\ReadSourceUnavailable;
use Simon\VentasPedidos\Application\PendingOrderQueries;
use Simon\VentasPedidos\Domain\OrderStatus;

final class SqlPendingOrders implements PendingOrderQueries
{
    public function quantities(array $variantIds): array
    {
        try {
            $pairs = DB::table('business_orders as orders')->join('business_order_lines as lines', 'lines.order_id', '=', 'orders.id')
                ->whereIn('orders.status', [OrderStatus::Confirmed->value, OrderStatus::Partial->value])
                ->whereColumn('lines.quantity', '>', 'lines.dispatched')->whereIn('lines.variant_id', $variantIds)
                ->selectRaw('lines.variant_id, SUM(lines.quantity - lines.dispatched) as pairs')->groupBy('lines.variant_id')->pluck('pairs', 'variant_id');

            return array_replace(array_fill_keys($variantIds, 0), $pairs->map(fn (mixed $quantity): int => (int) $quantity)->all());
        } catch (QueryException $exception) {
            throw new ReadSourceUnavailable('No se pudieron consultar los pedidos pendientes.', previous: $exception);
        }
    }

    public function snapshot(?int $sellerId, ?string $origin, int $customerPage, int $articlePage, bool $includeCustomers): array
    {
        return DB::transaction(function () use ($sellerId, $origin, $customerPage, $articlePage, $includeCustomers): array {
            $pending = DB::table('business_orders as orders')->join('business_order_lines as lines', 'lines.order_id', '=', 'orders.id')
                ->whereIn('orders.status', [OrderStatus::Confirmed->value, OrderStatus::Partial->value])->whereColumn('lines.quantity', '>', 'lines.dispatched')
                ->when($sellerId !== null, fn (Builder $query): Builder => $query->where('orders.seller_id', $sellerId))
                ->when($origin !== null, fn (Builder $query): Builder => $query->where('orders.origin', $origin));
            $totals = (clone $pending)->selectRaw('COALESCE(SUM(lines.quantity - lines.dispatched), 0) as pairs, COUNT(DISTINCT orders.id) as orders, COUNT(DISTINCT lines.variant_id) as articles')->first();
            $articles = (clone $pending)->selectRaw('lines.variant_id, SUM(lines.quantity - lines.dispatched) as pairs, COUNT(DISTINCT orders.id) as orders')
                ->groupBy('lines.variant_id')->orderBy('lines.variant_id');
            $customers = (clone $pending)->selectRaw('orders.customer_id, lines.variant_id, SUM(lines.quantity - lines.dispatched) as pairs, COUNT(DISTINCT orders.id) as orders')
                ->groupBy('orders.customer_id', 'lines.variant_id')->orderBy('orders.customer_id')->orderBy('lines.variant_id');

            return [
                'totals' => ['pairs' => (int) $totals->pairs, 'orders' => (int) $totals->orders, 'articles' => (int) $totals->articles],
                'customers' => $includeCustomers ? $this->page($customers, $customerPage) : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0],
                'articles' => $this->page($articles, $articlePage),
            ];
        });
    }

    /** @return array{items:list<array<string,int>>,page:int,pages:int,total:int} */
    private function page(Builder $query, int $page): array
    {
        $total = DB::query()->fromSub((clone $query)->reorder(), 'pending_groups')->count();
        $pages = max(1, (int) ceil($total / 25));
        $page = min($page, $pages);
        $items = (clone $query)->offset(($page - 1) * 25)->limit(25)->get()
            ->map(fn (object $row): array => array_map(fn (mixed $value): int => (int) $value, (array) $row))->all();

        return ['items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
