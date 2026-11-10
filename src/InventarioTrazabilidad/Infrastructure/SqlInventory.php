<?php

namespace Simon\InventarioTrazabilidad\Infrastructure;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\InventarioTrazabilidad\Application\InventorySetup;
use Simon\InventarioTrazabilidad\Application\InventoryStore;
use Simon\Shared\Application\Actor;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlInventory implements CatalogVariants, InventorySetup, InventoryStore, UnitOfWork
{
    public function identified(array $ids): array
    {
        return DB::table('catalog_variants')->whereIn('id', $ids)->get(['id', 'code', 'model', 'color', 'size'])
            ->map(fn (object $variant): array => (array) $variant)->all();
    }

    public function options(): array
    {
        return DB::table('catalog_variants')->orderBy('code')->limit(500)->get()->all();
    }

    public function missing(array $variantIds): array
    {
        $existing = DB::table('catalog_variants')->whereIn('id', $variantIds)->pluck('id')->all();

        return array_values(array_diff($variantIds, $existing));
    }

    public function variant(string $code, string $model, string $color, string $size): int
    {
        try {
            return DB::table('catalog_variants')->insertGetId(['code' => $code, 'model' => $model, 'color' => $color, 'size' => $size]);
        } catch (QueryException $exception) {
            throw new BusinessRuleViolation('Ese código o combinación de modelo, color y talle ya existe.', previous: $exception);
        }
    }

    public function createProduction(string $operationId, int $variantId, string $lot, string $manufacturedOn, int $quantity): int
    {
        return $this->run(function () use ($operationId, $variantId, $lot, $manufacturedOn, $quantity): int {
            $previous = DB::table('production_batches')->where('registration_id', $operationId)->first();

            if ($previous !== null) {
                throw new BusinessRuleViolation('Esta tanda ya fue registrada; consultá la lista antes de repetirla.');
            }

            if (! DB::table('catalog_variants')->where('id', $variantId)->exists()) {
                throw new BusinessRuleViolation('El artículo no existe.');
            }

            return DB::table('production_batches')->insertGetId([
                'registration_id' => $operationId, 'variant_id' => $variantId, 'lot' => $lot,
                'manufactured_on' => $manufacturedOn, 'in_process' => $quantity,
            ]);
        });
    }

    /** @return array{variants:list<object>, production:list<object>, stock:list<object>} */
    public function overview(bool $withStock = true): array
    {
        return [
            'variants' => $this->options(),
            'production' => $withStock ? DB::table('production_batches')->where('in_process', '>', 0)->orderByDesc('id')->limit(500)->get()->all() : [],
            'stock' => $withStock ? DB::table('inventory_batches')->where('quantity', '>', 0)->orderByDesc('id')->limit(500)->get()->all() : [],
        ];
    }

    /** @return list<object> */
    public function recentAudit(): array
    {
        return DB::table('inventory_audits')->orderByDesc('sequence')->limit(100)->get()->all();
    }

    public function run(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            DB::table('inventory_audit_head')->where('id', 1)->increment('lock_revision');

            return $operation();
        }, 5);
    }

    /** @param array<string, int|string|bool> $request */
    private function requestDigest(array $request): string
    {
        ksort($request);

        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
    }

    public function replay(string $operationId, int $actorId, array $request): ?int
    {
        $previous = DB::table('inventory_movements')->where('operation_id', $operationId)->first();

        if ($previous === null) {
            return null;
        }

        if ($previous->actor_id !== $actorId || ! hash_equals($previous->request_digest, $this->requestDigest($request))) {
            throw new BusinessRuleViolation('La identificación ya pertenece a otra operación.');
        }

        return $previous->id;
    }

    public function stock(int $batchId): array
    {
        $batch = DB::table('inventory_batches')->where('id', $batchId)->first();

        if ($batch === null) {
            throw new BusinessRuleViolation('El lote de depósito no existe.');
        }

        return (array) $batch;
    }

    public function production(int $batchId): array
    {
        $batch = DB::table('production_batches')->where('id', $batchId)->first();

        if ($batch === null) {
            throw new BusinessRuleViolation('La tanda en proceso no existe.');
        }

        return (array) $batch;
    }

    public function receive(int $productionId, int $quantity): int
    {
        $batch = $this->production($productionId);
        $updated = DB::table('production_batches')->where('id', $productionId)->where('in_process', '>=', $quantity)->decrement('in_process', $quantity);

        if ($updated !== 1) {
            throw new BusinessRuleViolation('La cantidad supera los pares que quedan en proceso.');
        }

        return $this->opening($batch['variant_id'], $batch['lot'], $batch['manufactured_on'], $quantity);
    }

    public function opening(int $variantId, string $lot, string $manufacturedOn, int $quantity): int
    {
        if (! DB::table('catalog_variants')->where('id', $variantId)->exists()) {
            throw new BusinessRuleViolation('El artículo no existe en el catálogo.');
        }

        $identity = ['variant_id' => $variantId, 'lot' => $lot, 'manufactured_on' => $manufacturedOn];
        $batch = DB::table('inventory_batches')->where($identity)->first();

        if ($batch === null) {
            return DB::table('inventory_batches')->insertGetId([...$identity, 'quantity' => $quantity]);
        }

        DB::table('inventory_batches')->where('id', $batch->id)->increment('quantity', $quantity);

        return $batch->id;
    }

    public function decrease(int $batchId, int $quantity): void
    {
        if (DB::table('inventory_batches')->where('id', $batchId)->where('quantity', '>=', $quantity)->decrement('quantity', $quantity) !== 1) {
            throw new BusinessRuleViolation('No hay pares suficientes en ese lote.');
        }
    }

    public function record(string $operationId, Actor $actor, string $kind, int $quantity, int $batchId, ?int $orderLineId, array $request, array $authorization = [], array $deliveryDestination = [], array $shipment = []): int
    {
        $key = config('inventory.audit_key');

        if (! is_string($key) || strlen($key) < 32) {
            throw new BusinessRuleViolation('La auditoría requiere una clave de firma configurada antes de operar.');
        }

        $occurredAt = now()->utc()->format('Y-m-d H:i:s');
        $movementId = DB::table('inventory_movements')->insertGetId([
            'operation_id' => $operationId, 'request_digest' => $this->requestDigest($request),
            'actor_id' => $actor->id, 'kind' => $kind, 'quantity' => $quantity,
            'batch_id' => $batchId, 'order_line_id' => $orderLineId, 'occurred_at' => $occurredAt,
        ]);
        $head = DB::table('inventory_audit_head')->where('id', 1)->first();
        $sequence = $head->sequence + 1;
        $data = [
            'sequence' => $sequence, 'movement_id' => $movementId, 'operation_id' => $operationId,
            'request_digest' => $this->requestDigest($request), 'actor_id' => $actor->id,
            'actor_name' => $actor->name, 'kind' => $kind, 'quantity' => $quantity,
            'batch_id' => $batchId, 'batch' => $this->stock($batchId),
            'order_line_id' => $orderLineId, 'occurred_at' => $occurredAt,
            'authorization' => $authorization,
            'delivery_destination' => $deliveryDestination,
        ];

        if ($shipment !== []) {
            $data['shipment'] = $shipment;
        }

        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $digest = hash_hmac('sha256', $head->digest."\n".$payload, $key);
        DB::table('inventory_audits')->insert(['sequence' => $sequence, 'movement_id' => $movementId, 'payload' => $payload, 'previous_digest' => $head->digest, 'digest' => $digest]);
        DB::table('inventory_audit_head')->where('id', 1)->update(['sequence' => $sequence, 'digest' => $digest]);

        return $movementId;
    }

    /** @return array{sequence:int, digest:string} */
    public function verify(): array
    {
        return $this->run(fn (): array => $this->verifySnapshot());
    }

    /** @return array{sequence:int, digest:string} */
    private function verifySnapshot(): array
    {
        $key = config('inventory.audit_key');
        $sequence = 0;
        $previous = str_repeat('0', 64);

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('La clave de auditoría no está disponible.');
        }

        foreach (DB::table('inventory_audits')->orderBy('sequence')->cursor() as $entry) {
            $sequence++;
            $digest = hash_hmac('sha256', $previous."\n".$entry->payload, $key);
            $data = json_decode($entry->payload, true, flags: JSON_THROW_ON_ERROR);
            $movement = DB::table('inventory_movements')->where('id', $entry->movement_id)->first();

            if ($entry->sequence !== $sequence || $entry->previous_digest !== $previous || ! hash_equals($entry->digest, $digest) || $movement === null) {
                throw new RuntimeException('Se detectó una inconsistencia en la auditoría.');
            }

            foreach (['id' => 'movement_id', 'actor_id' => 'actor_id', 'kind' => 'kind', 'quantity' => 'quantity', 'batch_id' => 'batch_id', 'order_line_id' => 'order_line_id', 'occurred_at' => 'occurred_at', 'operation_id' => 'operation_id', 'request_digest' => 'request_digest'] as $column => $field) {
                if ($movement->{$column} != $data[$field]) {
                    throw new RuntimeException('Un movimiento no coincide con su firma.');
                }
            }

            $previous = $digest;
        }

        $head = DB::table('inventory_audit_head')->where('id', 1)->first();

        if ($head->sequence !== $sequence || $head->digest !== $previous || DB::table('inventory_movements')->count() !== $sequence) {
            throw new RuntimeException('El cierre de auditoría no coincide con los movimientos.');
        }

        return ['sequence' => $sequence, 'digest' => $previous];
    }
}
