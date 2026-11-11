@extends('shared::layout')
@section('title', $pageTitle)
@section('content')
<x-filament::section heading="Alertas de vencimiento" class="simon-section">
    <p>Las advertencias conservan el insumo, su lote y la tanda vinculada. El vencimiento no bloquea automáticamente la producción ni el ingreso a depósito.</p>
    @if($report['anticipation_days'] === null)<p role="status">Falta configurar con cuántos días de anticipación se avisa antes del vencimiento.</p>@else<p>Anticipación configurada: {{ $report['anticipation_days'] }} días. Fecha de consulta: {{ $report['today'] }}.</p>@endif
    @if($canManage)
    <form class="simon-operation-form" method="post" action="{{ route('supplies.configure') }}">@csrf
        <label>Días de anticipación<x-simon::input name="anticipation_days" type="number" min="0" max="3650" required :value="$report['anticipation_days']" /></label>
        <x-filament::button type="submit">Guardar anticipación</x-filament::button>
    </form>
    @endif
</x-filament::section>
@if($canManage)
<x-filament::section heading="Registrar lote de insumo" class="simon-section">
    <form class="simon-operation-form" method="post" action="{{ route('supplies.store') }}">@csrf
        <x-simon::input type="hidden" name="operation_id" :value="$operationId" />
        <label>Insumo<x-simon::input name="name" required maxlength="150" /></label>
        <label>Lote del insumo<x-simon::input name="lot" required maxlength="100" /></label>
        <label>Vencimiento<x-simon::input name="expires_on" type="date" required /></label>
        <label>Tanda de producción relacionada (opcional)<x-simon::input name="production_id" type="number" min="1" /></label>
        <x-filament::button type="submit">Registrar insumo</x-filament::button>
    </form>
</x-filament::section>
@endif
<x-filament::section heading="Lotes y advertencias" class="simon-section">
    <div class="simon-table"><table><thead><tr><th>Insumo</th><th>Lote</th><th>Vencimiento</th><th>Advertencia</th><th>Tanda</th></tr></thead><tbody>
    @forelse($report['items'] as $lot)
        <tr><td>{{ $lot['name'] }}</td><td>{{ $lot['lot'] }}</td><td>{{ $lot['expires_on'] }}</td><td><x-filament::badge :color="in_array($lot['warning'], ['Vencido', 'Vence hoy']) ? 'danger' : ($lot['warning'] === 'Próximo a vencer' ? 'warning' : 'gray')">{{ $lot['warning'] }}</x-filament::badge></td><td>{{ $lot['production_id'] === null ? 'Sin tanda vinculada' : '#'.$lot['production_id'] }}</td></tr>
    @empty
        <tr><td colspan="5">No hay lotes de insumos registrados.</td></tr>
    @endforelse
    </tbody></table></div>
    <nav aria-label="Páginas de insumos" class="flex flex-wrap gap-4"><span>Página {{ $report['page'] }} de {{ $report['pages'] }} · {{ $report['total'] }} lotes.</span>
        @if($report['page'] > 1)<x-filament::link :href="route('supplies.index', ['page' => $report['page'] - 1])">Anterior</x-filament::link>@endif
        @if($report['page'] < $report['pages'])<x-filament::link :href="route('supplies.index', ['page' => $report['page'] + 1])">Siguiente</x-filament::link>@endif
    </nav>
</x-filament::section>
@endsection
