@extends('shared::layout')
@section('title', $pageTitle)
@section('content')
<x-filament::section heading="Identificación individual" class="simon-section">
    <p>Cada código corresponde a un solo par. El lote de fábrica sigue agrupando la tanda. Identificá y etiquetá físicamente los pares antes de leerlos para picking.</p>
    @if($canIdentify)
    <form class="simon-operation-form" method="post" action="{{ route('picking.identify') }}">@csrf
        <x-simon::input type="hidden" name="operation_id" :value="$operationId" />
        <label>Registro del lote de depósito<x-simon::input type="number" name="batch_id" required min="1" :value="$batchId" /></label>
        <x-filament::button type="submit">Asignar códigos a los pares del lote</x-filament::button>
    </form>
    @endif
    <x-filament::link :href="route('operations.stock')">Consultar registros y cantidades en Stock</x-filament::link>
</x-filament::section>
@livewire(\Simon\InventarioTrazabilidad\UI\PickingSelection::class, ['lineId' => $lineId, 'batchId' => $batchId, 'page' => $report['page']])
@endsection
