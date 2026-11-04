@php
    $variantLabels = collect($variants)->mapWithKeys(fn($variant) => [$variant->id => $variant->code.' · '.$variant->model.' / '.$variant->color.' / '.$variant->size]);
    $orderLabels = collect($orders)->mapWithKeys(fn($order) => [$order->id => $order->number.' · '.$order->origin.' · '.$order->status]);
@endphp
@if($withStock)
<section class="operations-card"><h2>Stock físico en depósito</h2><div class="operations-table"><table><thead><tr><th>Registro</th><th>Artículo</th><th>Lote de fábrica</th><th>Fabricación</th><th>Pares</th></tr></thead><tbody>
@forelse($stock as $batch)<tr><td>#{{ $batch->id }}</td><td>{{ $variantLabels[$batch->variant_id] ?? $batch->variant_id }}</td><td>{{ $batch->lot }}</td><td>{{ $batch->manufactured_on }}</td><td>{{ $batch->quantity }}</td></tr>@empty<tr><td colspan="5">Todavía no hay existencias registradas.</td></tr>@endforelse
</tbody></table></div></section>
<section class="operations-card"><h2>Producción en proceso</h2><div class="operations-table"><table><thead><tr><th>Tanda</th><th>Artículo</th><th>Lote</th><th>Fabricación</th><th>Pares en proceso</th></tr></thead><tbody>
@forelse($production as $batch)<tr><td>#{{ $batch->id }}</td><td>{{ $variantLabels[$batch->variant_id] ?? $batch->variant_id }}</td><td>{{ $batch->lot }}</td><td>{{ $batch->manufactured_on }}</td><td>{{ $batch->in_process }}</td></tr>@empty<tr><td colspan="5">Sin tandas pendientes de alta.</td></tr>@endforelse
</tbody></table></div></section>
@endif
<section class="operations-card"><h2>Mercadería pendiente por pedido</h2><p>Las cantidades representan pares por entregar. La deuda monetaria de cuenta corriente se gestiona por separado.</p>
<div class="operations-table"><table><thead><tr><th>Línea</th><th>Pedido</th><th>Artículo</th><th>Pedidos</th><th>Despachados</th><th>Pendientes</th></tr></thead><tbody>
@forelse($lines as $line)<tr><td>#{{ $line->id }}</td><td>{{ $orderLabels[$line->order_id] ?? $line->order_id }}</td><td>{{ $variantLabels[$line->variant_id] ?? $line->variant_id }}</td><td>{{ $line->quantity }}</td><td>{{ $line->dispatched }}</td><td>{{ $line->quantity - $line->dispatched }}</td></tr>@empty<tr><td colspan="6">Sin pedidos registrados para consultar.</td></tr>@endforelse
</tbody></table></div><p>Actualizado {{ now()->format('H:i:s') }}.</p></section>
