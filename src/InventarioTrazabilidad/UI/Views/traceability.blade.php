@extends('shared::layout')
@section('title', 'Trazabilidad de lotes')
@section('content')
<x-filament::section heading="Buscar despachos por lote y fabricación" class="simon-section">
    <p>Consultá las salidas históricas, incluidas las entregas parciales y los retiros de propietarios. El lote identifica una tanda de fabricación.</p>
    <form method="get" action="{{ route('traceability.index') }}">
        <div class="simon-grid">
            <label>Lote completo<x-simon::input name="lot" :value="$search['lot']" maxlength="100" /></label>
            <label>Fabricado desde<x-simon::input name="manufactured_from" type="date" :value="$search['manufactured_from']" /></label>
            <label>Fabricado hasta<x-simon::input name="manufactured_to" type="date" :value="$search['manufactured_to']" /></label>
        </div>
        <p>Ingresá el lote exacto, una fecha o un intervalo de fabricación. Podés combinar los campos para precisar la búsqueda.</p>
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Buscar historial</x-filament::button>
            <x-filament::link :href="route('traceability.index')">Limpiar búsqueda</x-filament::link>
        </div>
    </form>
</x-filament::section>
@if($searchError !== null)
    <x-filament::section heading="No se pudo completar la consulta" icon="heroicon-o-exclamation-triangle" icon-color="danger"><p role="alert">{{ $searchError }}</p></x-filament::section>
@elseif(!$searched)
    <x-filament::section><p>Indicá al menos un criterio para consultar los despachos.</p></x-filament::section>
@else
<x-filament::section heading="Historial de despachos y retiros" class="simon-section">
    <p role="status">{{ $result['total'] }} movimientos encontrados · página {{ $result['page'] }} de {{ $result['pages'] }}.</p>
    <p>Los destinos corresponden a los guardados en cada entrega. En registros anteriores, la marca «Actual» indica que el nombre del cliente o del artículo se consulta con sus datos actuales.</p>
    <div class="simon-table"><table><thead><tr><th>Salida · fecha UTC</th><th>Lote / fabricación</th><th>Artículo</th><th>Pedido</th><th>Cliente</th><th>Destino de esta entrega</th></tr></thead><tbody>
    @forelse($result['items'] as $entry)
        <tr data-trace-movement="{{ $entry['id'] }}">
            <td class="min-w-40"><strong>#{{ $entry['id'] }} · {{ $entry['quantity'] }} pares</strong><p>{{ $entry['kind'] === 'owner_withdrawal' ? 'Retiro de propietarios' : 'Despacho' }}<br>{{ (new \DateTimeImmutable($entry['occurred_at']))->format('d/m/Y H:i:s') }}<br>{{ $entry['actor_name'] }}</p><x-filament::badge :color="$entry['stored_identity'] ? 'success' : 'gray'" :title="$entry['stored_identity'] ? 'Datos guardados en la entrega' : 'Referencia actual de cliente y artículo'">{{ $entry['stored_identity'] ? 'Guardado' : 'Actual' }}</x-filament::badge></td>
            <td>{{ $entry['lot'] }}<br>{{ $entry['manufactured_on'] }}</td>
            <td>{{ $entry['article']['code'] ?? '#'.$entry['variant_id'] }}<br>{{ $entry['article']['model'] ?? 'Sin descripción registrada' }} / {{ $entry['article']['color'] ?? '—' }} / {{ $entry['article']['size'] ?? '—' }}</td>
            <td>{{ $entry['order']['number'] ?? 'Sin pedido identificado' }}<br>{{ $entry['order']['origin'] ?? '' }}</td>
            <td>{{ $entry['customer']['name'] ?? 'Sin cliente identificado' }}<br>{{ $entry['customer']['cuit'] ?? '' }}</td>
            <td>@include('clientes::destination', ['destination' => $entry['delivery_destination']])</td>
        </tr>
    @empty
        <tr><td colspan="6">No se encontraron despachos con esos criterios.</td></tr>
    @endforelse
    </tbody></table></div>
    <nav aria-label="Páginas del historial" class="flex flex-wrap gap-4">
        @if($result['page'] > 1)<x-filament::link :href="route('traceability.index', [...$search, 'page' => $result['page'] - 1])">Anterior</x-filament::link>@endif
        @if($result['page'] < $result['pages'])<x-filament::link :href="route('traceability.index', [...$search, 'page' => $result['page'] + 1])">Siguiente</x-filament::link>@endif
    </nav>
</x-filament::section>
@endif
@endsection
