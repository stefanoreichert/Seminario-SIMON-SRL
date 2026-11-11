<?php

namespace Simon\InventarioTrazabilidad\Infrastructure;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Simon\InventarioTrazabilidad\Application\ProductionPlanningQueries;
use Simon\Shared\Application\ReadSourceUnavailable;

final class SqlProductionPlanning implements ProductionPlanningQueries
{
    public function snapshot(Closure $operation): array
    {
        try {
            return DB::transaction(function () use ($operation): array {
                $head = DB::table('inventory_audit_head')->where('id', 1)->sharedLock()->first(['id']);

                if ($head === null) {
                    throw new ReadSourceUnavailable('No está disponible el control de consistencia de inventario.');
                }

                return $operation();
            }, 3);
        } catch (QueryException $exception) {
            throw new ReadSourceUnavailable('No se pudieron consultar las existencias y la producción en proceso.', previous: $exception);
        }
    }

    public function page(int $page): array
    {
        $total = DB::table('catalog_variants')->count();
        $pages = max(1, (int) ceil($total / 25));
        $page = min($page, $pages);
        $variants = DB::table('catalog_variants')->orderBy('id')->offset(($page - 1) * 25)->limit(25)->get(['id', 'code', 'model', 'color', 'size']);
        $ids = $variants->pluck('id')->all();
        $stock = DB::table('inventory_batches')->whereIn('variant_id', $ids)->selectRaw('variant_id, SUM(quantity) as pairs')->groupBy('variant_id')->pluck('pairs', 'variant_id');
        $production = DB::table('production_batches')->whereIn('variant_id', $ids)->selectRaw('variant_id, SUM(in_process) as pairs')->groupBy('variant_id')->pluck('pairs', 'variant_id');
        $items = $variants->map(fn (object $variant): array => [
            'id' => (int) $variant->id, 'code' => $variant->code, 'model' => $variant->model, 'color' => $variant->color, 'size' => $variant->size,
            'available' => (int) ($stock[$variant->id] ?? 0), 'in_process' => (int) ($production[$variant->id] ?? 0),
        ])->all();

        return ['items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
