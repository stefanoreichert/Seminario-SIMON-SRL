<?php

namespace Simon\InventarioTrazabilidad\Infrastructure;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;
use Simon\InventarioTrazabilidad\Application\TraceabilityRecords;
use Simon\InventarioTrazabilidad\Domain\TraceabilityFilter;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlTraceability implements TraceabilityRecords
{
    public function search(TraceabilityFilter $filter, int $page): array
    {
        try {
            return DB::transaction(function () use ($filter, $page): array {
                $query = DB::table('inventory_audits as audits')->join('inventory_movements as movements', 'movements.id', '=', 'audits.movement_id')
                    ->whereIn('movements.kind', ['dispatch', 'owner_withdrawal'])
                    ->when($filter->lot !== null, fn ($query) => $query->where('audits.payload->batch->lot', $filter->lot))
                    ->when($filter->manufacturedFrom !== null, fn ($query) => $query->where('audits.payload->batch->manufactured_on', '>=', $filter->manufacturedFrom))
                    ->when($filter->manufacturedTo !== null, fn ($query) => $query->where('audits.payload->batch->manufactured_on', '<=', $filter->manufacturedTo));
                $total = (clone $query)->count();
                $pages = max(1, (int) ceil($total / 25));
                $page = min($page, $pages);
                $entries = $query->orderByDesc('movements.id')->offset(($page - 1) * 25)->limit(25)
                    ->get(['movements.*', 'audits.sequence', 'audits.payload', 'audits.previous_digest', 'audits.digest']);
                $previous = DB::table('inventory_audits')->whereIn('sequence', $entries->map(fn (object $entry): int => $entry->sequence - 1))->pluck('digest', 'sequence')->all();
                $key = config('inventory.audit_key');

                if (! is_string($key) || strlen($key) < 32) {
                    throw new BusinessRuleViolation('La clave de verificación del historial no está disponible. Contactá a administración.');
                }

                $items = $entries->map(function (object $entry) use ($previous, $key): array {
                    $data = json_decode($entry->payload, true, flags: JSON_THROW_ON_ERROR);
                    $expectedPrevious = (int) $entry->sequence === 1 ? str_repeat('0', 64) : ($previous[$entry->sequence - 1] ?? null);

                    if ($entry->previous_digest !== $expectedPrevious || ! hash_equals($entry->digest, hash_hmac('sha256', $entry->previous_digest."\n".$entry->payload, $key))) {
                        $this->integrityFailure();
                    }

                    foreach (['id' => 'movement_id', 'sequence' => 'sequence', 'actor_id' => 'actor_id', 'kind' => 'kind', 'quantity' => 'quantity', 'batch_id' => 'batch_id', 'order_line_id' => 'order_line_id', 'occurred_at' => 'occurred_at', 'operation_id' => 'operation_id', 'request_digest' => 'request_digest'] as $column => $field) {
                        if (($data[$field] ?? null) != $entry->{$column}) {
                            $this->integrityFailure();
                        }
                    }

                    return [
                        'id' => (int) $entry->id, 'sequence' => (int) $entry->sequence, 'occurred_at' => $data['occurred_at'],
                        'actor_name' => $data['actor_name'], 'kind' => $data['kind'], 'quantity' => $data['quantity'],
                        'variant_id' => $data['batch']['variant_id'], 'lot' => $data['batch']['lot'], 'manufactured_on' => $data['batch']['manufactured_on'],
                        'order_line_id' => $data['order_line_id'], 'shipment' => $data['shipment'] ?? null,
                        'delivery_destination' => ($data['delivery_destination'] ?? []) === [] ? null : $data['delivery_destination'],
                    ];
                })->all();

                return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
            });
        } catch (QueryException|JsonException $exception) {
            throw new BusinessRuleViolation('No se pudo consultar el historial. Contactá a administración para revisar los registros.', previous: $exception);
        }
    }

    private function integrityFailure(): never
    {
        throw new BusinessRuleViolation('El historial consultado no superó la comprobación de integridad. Contactá a administración.');
    }
}
