@php
    $variantLabels = collect($variants)->mapWithKeys(fn($variant) => [$variant->id => $variant->code.' · '.$variant->model.' / '.$variant->color.' / '.$variant->size]);
    $orderLabels = collect($orders)->mapWithKeys(fn($order) => [$order->id => $order->number.' · '.$order->status]);
    $orderOrigins = collect($orders)->mapWithKeys(fn($order) => [$order->id => $originLabels[$order->origin] ?? $order->origin]);
    $orderDestinations = collect($orders)->mapWithKeys(fn($order) => [$order->id => $order->delivery_destination]);
@endphp
@if($productionNeeds !== null || $planningUnavailable)
    @include('inventario::production-needs')
@endif
@if($pendingProduction !== null)
    @include('ventas::pending-summary', ['pendingReport' => $pendingProduction, 'withCustomers' => false, 'pendingPageRoute' => 'operations.production.index', 'pendingPageParams' => ['article_page' => $pendingProduction['articles']['page'], 'needs_page' => $productionNeeds['page'] ?? null]])
@endif
@if($section === 'orders')
<x-filament::section heading="Notas de pedido" class="simon-section">
    @if($originFilter !== null)<p role="status">Origen seleccionado: {{ $originLabels[$originFilter] }}</p>@endif
    <p>Hasta 200 notas recientes{{ $originFilter === null ? '' : ' del origen seleccionado' }}. Se muestran los pedidos permitidos para tu usuario.</p>
    <div class="simon-table"><table>
        <thead><tr><th>Pedido</th><th>Origen</th><th>Estado</th><th>Dirección de entrega</th></tr></thead>
        <tbody>
        @forelse($orders as $order)
            <tr><td><x-filament::link :href="route('orders.show', $order->id)">{{ $order->number }}</x-filament::link></td><td><x-filament::badge>{{ $orderOrigins[$order->id] }}</x-filament::badge></td><td>{{ $order->status }}</td><td>@include('clientes::destination', ['destination' => $order->delivery_destination])</td></tr>
        @empty
            <tr><td colspan="4">No hay notas de pedido para esta consulta.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</x-filament::section>
@endif
@if($showStock)
<x-filament::section class="simon-section"><x-slot name="heading"><span>Stock físico en depósito</span></x-slot><div class="simon-table"><table><thead><tr><th>Registro</th><th>Artículo</th><th>Lote de fábrica</th><th>Fabricación</th><th>Pares</th></tr></thead><tbody>
@forelse($stock as $batch)<tr><td>#{{ $batch->id }}</td><td>{{ $variantLabels[$batch->variant_id] ?? $batch->variant_id }}</td><td>{{ $batch->lot }}</td><td>{{ $batch->manufactured_on }}</td><td>{{ $batch->quantity }}</td></tr>@empty<tr><td colspan="5">Todavía no hay existencias registradas.</td></tr>@endforelse
</tbody></table></div></x-filament::section>
@endif
@if($showProduction)
<x-filament::section class="simon-section"><x-slot name="heading"><span>Producción en proceso</span></x-slot><div class="simon-table"><table><thead><tr><th>Tanda</th><th>Artículo</th><th>Lote</th><th>Fabricación</th><th>Pares en proceso</th></tr></thead><tbody>
@forelse($production as $batch)<tr><td>#{{ $batch->id }}</td><td>{{ $variantLabels[$batch->variant_id] ?? $batch->variant_id }}</td><td>{{ $batch->lot }}</td><td>{{ $batch->manufactured_on }}</td><td>{{ $batch->in_process }}</td></tr>@empty<tr><td colspan="5">Sin tandas pendientes de alta.</td></tr>@endforelse
</tbody></table></div></x-filament::section>
@endif
@if($showOrders)
<p>Este detalle corresponde a las últimas 200 notas de la vista. <x-filament::link :href="route('pending.index', ['origin' => $originFilter])">Consultar todos los pendientes por cliente y artículo</x-filament::link>.</p>
<x-filament::section class="simon-section"><x-slot name="heading"><span>Mercadería pendiente por pedido</span></x-slot><p>Solo Confirmado y Parcial generan pares pendientes. Borrador y Anulado conservan sus artículos sin generar saldo por entregar. Las cantidades representan pares por entregar. La deuda monetaria de cuenta corriente se gestiona por separado.</p>
<div class="simon-table"><table><thead><tr><th>Línea</th><th>Pedido</th><th>Origen</th><th>Artículo</th><th>Pedidos</th><th>Despachados</th><th>Pendientes</th><th>Destino del pedido</th></tr></thead><tbody>
@forelse($lines as $line)<tr><td>#{{ $line->id }}</td><td>{{ $orderLabels[$line->order_id] ?? $line->order_id }}</td><td>{{ $orderOrigins[$line->order_id] ?? '' }}</td><td>{{ $variantLabels[$line->variant_id] ?? $line->variant_id }}</td><td>{{ $line->quantity }}</td><td>{{ $line->dispatched }}</td><td>{{ $line->pending }}</td><td>@include('clientes::destination', ['destination' => $orderDestinations[$line->order_id] ?? null])</td></tr>@empty<tr><td colspan="8">Sin pedidos registrados para consultar.</td></tr>@endforelse
</tbody></table></div><p>Actualizado {{ now()->format('H:i:s') }}.</p></x-filament::section>

@endif
