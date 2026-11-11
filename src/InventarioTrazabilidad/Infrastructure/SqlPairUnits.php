<?php

namespace Simon\InventarioTrazabilidad\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simon\InventarioTrazabilidad\Application\PairUnits;
use Simon\InventarioTrazabilidad\Domain\PairUnit;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlPairUnits implements PairUnits
{
    private ?bool $installed = null;

    private function installed(): bool
    {
        return $this->installed ??= Schema::hasTable('inventory_pair_units') && Schema::hasTable('inventory_tracked_batches');
    }

    public function replay(string $operationId, int $actorId, array $request): ?int
    {
        $previous = DB::table('inventory_pair_operations')->where('operation_id', $operationId)->first();

        if ($previous === null) {
            return null;
        }

        if ((int) $previous->actor_id !== $actorId || ! hash_equals($previous->request_digest, $this->digest($request))) {
            throw new BusinessRuleViolation('La identificación de operación ya pertenece a otro picking.');
        }

        return (int) $previous->id;
    }

    public function record(string $operationId, int $actorId, array $request): int
    {
        return DB::table('inventory_pair_operations')->insertGetId([
            'operation_id' => $operationId, 'actor_id' => $actorId, 'request_digest' => $this->digest($request),
            'request' => json_encode($request, JSON_THROW_ON_ERROR), 'occurred_at' => now()->utc()->format('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,int|string> $request */
    private function digest(array $request): string
    {
        ksort($request);

        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
    }

    public function tracked(int $batchId): bool
    {
        return $this->installed() && DB::table('inventory_tracked_batches')->where('batch_id', $batchId)->exists();
    }

    public function identify(int $batchId, int $quantity, int $actorId): void
    {
        DB::table('inventory_tracked_batches')->insert(['batch_id' => $batchId, 'actor_id' => $actorId, 'activated_at' => now()->utc()->format('Y-m-d H:i:s')]);
        $this->increaseIfTracked($batchId, $quantity);
    }

    public function increaseIfTracked(int $batchId, int $quantity): void
    {
        if (! $this->tracked($batchId)) {
            return;
        }

        while ($quantity > 0) {
            $count = min(500, $quantity);
            $rows = [];

            for ($index = 0; $index < $count; $index++) {
                $rows[] = ['code' => 'PAR-'.strtoupper(bin2hex(random_bytes(12))), 'batch_id' => $batchId, 'state' => 'available'];
            }

            DB::table('inventory_pair_units')->insert($rows);
            $quantity -= $count;
        }
    }

    public function get(string $code): PairUnit
    {
        $row = DB::table('inventory_pair_units as units')->join('inventory_batches as batches', 'batches.id', '=', 'units.batch_id')
            ->where('units.code', $code)->first(['units.*', 'batches.variant_id']);

        if ($row === null) {
            throw new BusinessRuleViolation('Código desconocido. Verificá la lectura o identificá primero el lote.');
        }

        return new PairUnit($row->code, (int) $row->batch_id, (int) $row->variant_id, $row->state, $row->order_line_id === null ? null : (int) $row->order_line_id);
    }

    public function save(PairUnit $unit, int $actorId, ?int $movementId = null): void
    {
        DB::table('inventory_pair_units')->where('code', $unit->code)->update([
            'state' => $unit->state(), 'order_line_id' => $unit->orderLineId(),
            'picked_by' => $unit->state() === 'available' ? null : $actorId, 'movement_id' => $movementId,
        ]);
    }

    public function pickedCount(int $lineId): int
    {
        return $this->installed() ? DB::table('inventory_pair_units')->where('order_line_id', $lineId)->where('state', 'picked')->count() : 0;
    }

    public function overview(?int $lineId, ?int $batchId, int $page): array
    {
        $query = DB::table('inventory_pair_units as units')->join('inventory_batches as batches', 'batches.id', '=', 'units.batch_id')
            ->join('catalog_variants as variants', 'variants.id', '=', 'batches.variant_id')
            ->when($batchId !== null, fn ($query) => $query->where('units.batch_id', $batchId));
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / 50));
        $page = min($page, $pages);
        $items = (clone $query)->orderBy('units.id')->offset(($page - 1) * 50)->limit(50)
            ->get(['units.*', 'batches.lot', 'batches.manufactured_on', 'variants.code as article_code', 'variants.model', 'variants.color', 'variants.size'])
            ->map(fn (object $row): array => (array) $row)->all();
        $selected = $lineId === null ? [] : DB::table('inventory_pair_units')->where('order_line_id', $lineId)->where('state', 'picked')->orderBy('batch_id')->orderBy('code')
            ->get(['code', 'batch_id'])->map(fn (object $row): array => (array) $row)->all();

        return ['items' => $items, 'selected' => $selected, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
