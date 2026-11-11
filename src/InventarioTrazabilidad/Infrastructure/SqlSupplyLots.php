<?php

namespace Simon\InventarioTrazabilidad\Infrastructure;

use Illuminate\Support\Facades\DB;
use Simon\InventarioTrazabilidad\Application\SupplyLots;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlSupplyLots implements SupplyLots
{
    public function configure(int $anticipationDays): void
    {
        DB::table('inventory_expiry_settings')->where('id', 1)->update(['anticipation_days' => $anticipationDays]);
    }

    public function register(string $operationId, int $actorId, string $name, string $lot, string $expiresOn, ?int $productionId): int
    {
        $data = ['actor_id' => $actorId, 'name' => $name, 'lot' => $lot, 'expires_on' => $expiresOn, 'production_id' => $productionId];
        $previous = DB::table('inventory_supply_lots')->where('operation_id', $operationId)->first();

        if ($previous !== null) {
            foreach ($data as $key => $value) {
                if ($previous->{$key} != $value) {
                    throw new BusinessRuleViolation('La operación ya corresponde a otro lote de insumo.');
                }
            }

            return (int) $previous->id;
        }

        if ($productionId !== null && ! DB::table('production_batches')->where('id', $productionId)->exists()) {
            throw new BusinessRuleViolation('La tanda vinculada al insumo no existe.');
        }

        return DB::table('inventory_supply_lots')->insertGetId([...$data, 'operation_id' => $operationId, 'registered_at' => now()->utc()->format('Y-m-d H:i:s')]);
    }

    public function overview(int $page): array
    {
        return DB::transaction(function () use ($page): array {
            $total = DB::table('inventory_supply_lots')->count();
            $pages = max(1, (int) ceil($total / 25));
            $page = min($page, $pages);

            return [
                'today' => now()->toDateString(),
                'anticipation_days' => DB::table('inventory_expiry_settings')->where('id', 1)->value('anticipation_days'),
                'items' => DB::table('inventory_supply_lots')->orderBy('expires_on')->orderBy('id')->offset(($page - 1) * 25)->limit(25)->get()
                    ->map(fn (object $row): array => (array) $row)->all(),
                'page' => $page, 'pages' => $pages, 'total' => $total,
            ];
        });
    }
}
