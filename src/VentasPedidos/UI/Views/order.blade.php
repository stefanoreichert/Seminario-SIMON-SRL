@extends('shared::layout')
@section('title', $order['number'].' · '.$order['status'])
@section('content')
<x-filament::link :href="route('operations.orders', ['origin' => $order['origin']])" icon="heroicon-o-arrow-left">Volver a pedidos</x-filament::link>
<x-filament::section heading="Detalle de la nota" class="simon-section">
    <p>Cliente: {{ collect($customers)->firstWhere('id', $order['customer_id'])?->name ?? '#'.$order['customer_id'] }} · Origen: {{ $originLabels[$order['origin']] }} · Estado: {{ $order['status'] }}</p>
    @include('clientes::destination', ['destination' => $order['delivery_destination']])
    <div class="simon-table"><table>
        <thead><tr><th>Artículo</th><th>Pedidos</th><th>Entregados</th><th>Pendientes</th></tr></thead>
        <tbody>
        @foreach($order['lines'] as $line)
            @php($variant = collect($variants)->firstWhere('id', $line['variant_id']))
            <tr><td>{{ $variant ? $variant->code.' · '.$variant->model.' / '.$variant->color.' / '.$variant->size : '#'.$line['variant_id'] }}</td><td>{{ $line['quantity'] }}</td><td>{{ $line['dispatched'] }}</td><td>{{ $state->acceptsDispatch() ? $line['quantity'] - $line['dispatched'] : 0 }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @if($state === \Simon\VentasPedidos\Domain\OrderStatus::Cancelled)
        <p>El saldo pendiente fue anulado. Las cantidades solicitadas y las entregas realizadas se conservan como historia; esta nota no admite más despachos.</p>
    @elseif($state !== \Simon\VentasPedidos\Domain\OrderStatus::Draft)
        <p>Los artículos confirmados se conservan. Para corregir un pedido, anulá su saldo pendiente y registrá una nota nueva. Las entregas realizadas se conservan.</p>
    @endif
</x-filament::section>
@if($mayChange && $state === \Simon\VentasPedidos\Domain\OrderStatus::Draft)
    @include('ventas::order-form', ['editingOrder' => $order])
    <x-filament::section heading="Confirmar borrador" class="simon-section">
        <p>Primero guardá los cambios. Confirmar utiliza los artículos y el destino guardados en esta nota.</p>
        <form method="post" action="{{ route('orders.transition', $order['id']) }}">
            @csrf
            <x-simon::input type="hidden" name="operation_id" :value="$confirmToken" />
            <x-simon::input type="hidden" name="revision" :value="$order['revision']" />
            <x-filament::button type="submit" name="action" value="confirm">Confirmar artículos guardados</x-filament::button>
        </form>
    </x-filament::section>
@endif
@if($mayChange && $state->isActive())
<x-filament::section heading="Anular saldo pendiente" class="simon-section">
    <p>Esta acción cierra la nota y cancela los pares aún no entregados. No devuelve mercadería al stock ni modifica entregas anteriores.</p>
    <form method="post" action="{{ route('orders.transition', $order['id']) }}">
        @csrf
        <x-simon::input type="hidden" name="operation_id" :value="$cancelToken" />
        <x-simon::input type="hidden" name="revision" :value="$order['revision']" />
        <label>Motivo de anulación<x-simon::textarea name="reason" required maxlength="1000">{{ old('reason') }}</x-simon::textarea></label>
        <x-filament::button type="submit" name="action" value="cancel" color="danger">Anular saldo pendiente</x-filament::button>
    </form>
</x-filament::section>
@endif
<x-filament::section heading="Cambios de la nota" class="simon-section">
    <div class="simon-table"><table><thead><tr><th>Fecha UTC</th><th>Usuario</th><th>Acción</th><th>Estado</th><th>Motivo</th></tr></thead><tbody>
    @forelse($order['changes'] as $change)
        <tr><td>{{ $change['recorded_at'] }}</td><td>{{ $change['actor_name'] }}</td><td>{{ ['edit' => 'Edición de borrador', 'confirm' => 'Confirmación', 'cancel' => 'Anulación de saldo'][$change['action']] }}</td><td>{{ $change['from_status'] }} → {{ $change['to_status'] }}</td><td>{{ $change['reason'] }}</td></tr>
    @empty
        <tr><td colspan="5">Sin cambios posteriores al registro. Las entregas se consultan en la auditoría de movimientos.</td></tr>
    @endforelse
    </tbody></table></div>
</x-filament::section>
@endsection
