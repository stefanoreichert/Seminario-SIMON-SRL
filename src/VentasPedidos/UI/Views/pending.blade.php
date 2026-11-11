@extends('shared::layout')
@section('title', 'Pedidos pendientes')
@section('content')
<x-filament::section heading="Consultar mercadería por entregar" class="simon-section">
    <p>Los totales incluyen todas las notas confirmadas y parciales permitidas para tu usuario. Los borradores y las notas anuladas o entregadas quedan fuera del saldo pendiente.</p>
    <form method="get" action="{{ route('pending.index') }}">
        <label>Origen
            <x-simon::select name="origin">
                <option value="">Todos los orígenes permitidos</option>
                @foreach($originLabels as $code => $label)<option value="{{ $code }}" @selected($originFilter === $code)>{{ $label }}</option>@endforeach
            </x-simon::select>
        </label>
        <x-filament::button type="submit">Aplicar filtro</x-filament::button>
        @if($originFilter !== null)<x-filament::link :href="route('pending.index')">Quitar filtro</x-filament::link>@endif
    </form>
</x-filament::section>
<p id="operations-sync" role="status" aria-live="polite">Los saldos se actualizan automáticamente.</p>
@livewire(\Simon\Shared\UI\LiveBalances::class, ['kind' => 'pending', 'parameters' => $pendingPageParams])
@endsection
