<?php

namespace Simon\VentasPedidos\Infrastructure;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\VentasPedidos\Application\DispatchableOrders;
use Simon\VentasPedidos\Application\OrderWorkflow;
use Simon\VentasPedidos\Domain\OrderFingerprint;
use Simon\VentasPedidos\Domain\OrderStatus;

final class SqlOrderWorkflow implements DispatchableOrders, OrderWorkflow
{
    public function dispatchable(int $orderId): bool
    {
        $status = DB::table('business_orders')->where('id', $orderId)->value('status');

        return $status !== null && OrderStatus::from($status)->acceptsDispatch();
    }

    public function find(int $orderId): ?array
    {
        return DB::transaction(function () use ($orderId): ?array {
            $order = DB::table('business_orders')->where('id', $orderId)->lockForUpdate()->first([
                'id', 'number', 'customer_id', 'seller_id', 'origin', 'status', 'revision', 'delivery_address_id', 'delivery_destination',
            ]);

            if ($order === null) {
                return null;
            }

            $order->delivery_destination = $order->delivery_destination === null ? null : json_decode($order->delivery_destination, true, flags: JSON_THROW_ON_ERROR);
            $lines = DB::table('business_order_lines')->where('order_id', $orderId)->orderBy('id')
                ->get(['id', 'variant_id', 'quantity', 'dispatched'])->map(fn (object $line): array => (array) $line)->all();
            $changes = DB::table('business_order_changes as changes')->join('users', 'users.id', '=', 'changes.actor_id')
                ->where('changes.order_id', $orderId)->orderBy('changes.id')
                ->get(['action', 'users.name as actor_name', 'from_status', 'to_status', 'reason', 'recorded_at'])
                ->map(fn (object $change): array => (array) $change)->all();

            return [...(array) $order, 'lines' => $lines, 'changes' => $changes];
        });
    }

    public function assertNoDuplicate(int $customerId, array $lines, ?int $exceptOrderId = null): void
    {
        $fingerprint = OrderFingerprint::make($customerId, $lines);
        $orders = DB::table('business_orders')->where('customer_id', $customerId)
            ->whereIn('status', [OrderStatus::Draft->value, OrderStatus::Confirmed->value, OrderStatus::Partial->value])
            ->when($exceptOrderId !== null, fn ($query) => $query->where('id', '<>', $exceptOrderId))->get(['id', 'number']);
        $existingLines = DB::table('business_order_lines')->whereIn('order_id', $orders->pluck('id'))->orderBy('id')
            ->get(['order_id', 'variant_id', 'quantity'])->groupBy('order_id');

        foreach ($orders as $order) {
            $items = ($existingLines->get($order->id) ?? collect())->map(fn (object $line): array => ['variant_id' => $line->variant_id, 'quantity' => $line->quantity])->all();

            if ($items !== [] && hash_equals($fingerprint, OrderFingerprint::make($customerId, $items))) {
                throw new BusinessRuleViolation('Ya existe el pedido activo '.$order->number.' para este cliente con los mismos artículos y cantidades. Consultalo antes de volver a cargarlo.');
            }
        }
    }

    public function saveDraft(int $orderId, int $revision, int $customerId, string $origin, array $lines, ?int $deliveryAddressId, ?array $destination): void
    {
        try {
            $updated = DB::table('business_orders')->where('id', $orderId)->where('revision', $revision)->where('status', OrderStatus::Draft->value)->update([
                'customer_id' => $customerId, 'origin' => $origin, 'delivery_address_id' => $deliveryAddressId,
                'delivery_destination' => $destination === null ? null : json_encode($destination, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'revision' => $revision + 1,
            ]);
            $this->assertUpdated($updated);
            DB::table('business_order_lines')->where('order_id', $orderId)->delete();

            foreach ($lines as $line) {
                DB::table('business_order_lines')->insert(['order_id' => $orderId, ...$line, 'dispatched' => 0]);
            }
        } catch (QueryException $exception) {
            throw new BusinessRuleViolation('No se pudo guardar el borrador. Revisá el cliente y los artículos.', previous: $exception);
        }
    }

    public function setStatus(int $orderId, int $revision, OrderStatus $status, ?array $destination = null): void
    {
        $values = ['status' => $status->value, 'revision' => $revision + 1];

        if ($status === OrderStatus::Confirmed) {
            $values['delivery_destination'] = $destination === null ? null : json_encode($destination, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $this->assertUpdated(DB::table('business_orders')->where('id', $orderId)->where('revision', $revision)
            ->update($values));
    }

    public function replay(string $operationId, int $actorId, array $request): bool
    {
        $previous = DB::table('business_order_changes')->where('operation_id', $operationId)->first();

        if ($previous === null) {
            return false;
        }

        if ($previous->actor_id !== $actorId || ! hash_equals($previous->request_digest, $this->digest($request))) {
            throw new BusinessRuleViolation('La identificación ya pertenece a otro cambio de pedido.');
        }

        return true;
    }

    public function record(string $operationId, int $actorId, array $request, OrderStatus $from, OrderStatus $to, ?string $reason = null): void
    {
        DB::table('business_order_changes')->insert([
            'operation_id' => $operationId, 'request_digest' => $this->digest($request), 'order_id' => $request['order_id'],
            'actor_id' => $actorId, 'action' => $request['action'], 'from_status' => $from->value, 'to_status' => $to->value,
            'reason' => $reason, 'recorded_at' => now(),
        ]);
    }

    private function assertUpdated(int $updated): void
    {
        if ($updated !== 1) {
            throw new BusinessRuleViolation('El pedido cambió desde que abriste la pantalla. Recargalo antes de continuar.');
        }
    }

    /** @param array<string,mixed> $request */
    private function digest(array $request): string
    {
        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
    }
}
