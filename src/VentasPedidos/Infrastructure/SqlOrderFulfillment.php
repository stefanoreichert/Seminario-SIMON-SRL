<?php

namespace Simon\VentasPedidos\Infrastructure;

use Illuminate\Support\Facades\DB;
use Simon\InventarioTrazabilidad\Application\OrderFulfillment;
use Simon\Shared\Domain\BusinessRuleViolation;

final class SqlOrderFulfillment implements OrderFulfillment
{
    public function line(int $lineId): array
    {
        $line = DB::table('business_order_lines')->where('id', $lineId)->first();

        if ($line === null) {
            throw new BusinessRuleViolation('La línea del pedido no existe.');
        }

        $order = DB::table('business_orders')->where('id', $line->order_id)->first();

        if ($order === null || ! in_array($order->status, ['Confirmado', 'Parcial'], true)) {
            throw new BusinessRuleViolation('El pedido no admite despachos en su estado actual.');
        }

        return [
            ...(array) $line, 'customer_id' => $order->customer_id,
            'delivery_destination' => $order->delivery_destination === null ? null : json_decode($order->delivery_destination, true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    public function dispatch(int $lineId, int $quantity): void
    {
        $line = $this->line($lineId);
        $updated = DB::table('business_order_lines')->where('id', $lineId)
            ->whereRaw('quantity - dispatched >= ?', [$quantity])->increment('dispatched', $quantity);

        if ($updated !== 1) {
            throw new BusinessRuleViolation('La cantidad supera el saldo de mercadería pendiente.');
        }

        $pending = DB::table('business_order_lines')->where('order_id', $line['order_id'])->whereColumn('dispatched', '<', 'quantity')->exists();
        DB::table('business_orders')->where('id', $line['order_id'])->update(['status' => $pending ? 'Parcial' : 'Entregado']);
    }
}
