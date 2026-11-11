@extends('shared::layout')
@section('title', 'Cuenta corriente')
@section('content')
<x-filament::section heading="Buscar cuenta por cliente" class="simon-section" collapsible :collapsed="$report['customer'] !== null">
    <form method="get" action="{{ route('current-accounts.index') }}"><label>Nombre o CUIT<x-simon::input name="q" :value="$query" maxlength="255" /></label><x-filament::button type="submit">Buscar cliente</x-filament::button></form>
    <div class="simon-table"><table><thead><tr><th>Cliente</th><th>CUIT</th><th>Cuenta</th></tr></thead><tbody>
    @forelse($report['customers']['items'] as $customer)<tr><td>{{ $customer['name'] }}</td><td>{{ $customer['cuit'] }}</td><td><x-filament::link :href="route('current-accounts.index', ['customer_id' => $customer['id'], 'q' => $query, 'customer_page' => $report['customers']['page']])">Consultar cuenta</x-filament::link></td></tr>@empty<tr><td colspan="3">No hay clientes para esta búsqueda.</td></tr>@endforelse
    </tbody></table></div>
    <nav class="flex flex-wrap gap-4" aria-label="Páginas de clientes de cuenta corriente"><span>Página {{ $report['customers']['page'] }} · {{ $report['customers']['total'] }} clientes.</span>
        @if($report['customers']['page'] > 1)<x-filament::link :href="route('current-accounts.index', ['q' => $query, 'customer_page' => $report['customers']['page'] - 1])">Anterior</x-filament::link>@endif
        @if($report['customers']['page'] * $report['customers']['per_page'] < $report['customers']['total'])<x-filament::link :href="route('current-accounts.index', ['q' => $query, 'customer_page' => $report['customers']['page'] + 1])">Siguiente</x-filament::link>@endif
    </nav>
</x-filament::section>
@if($report['customer'] !== null)
<x-filament::section :heading="$report['customer']['name'].' · '.$report['customer']['cuit']" class="simon-section">
    @if($balance === null)
        <p role="status" data-account-uninitialized>No hay saldo inicial registrado. La deuda monetaria todavía no está determinada.</p>
    @else
        <p data-account-balance="{{ $report['account']['balance_minor'] }}"><strong>Saldo: {{ $balance }}</strong></p>
        <p>Un saldo positivo es deuda del cliente; uno negativo es saldo a su favor. Los pares por entregar se consultan por separado en Pedidos.</p>
    @endif
    @if($canManage)
    <form method="post" action="{{ route('current-accounts.store') }}">@csrf
        <x-simon::input type="hidden" name="operation_id" :value="$operationId" /><x-simon::input type="hidden" name="customer_id" :value="$report['customer']['id']" />
        <label>Tipo de movimiento<x-simon::select name="kind" required>
            @if($balance === null)<option value="opening">Registrar saldo inicial</option>@else
                @foreach(\Simon\ClientesAdministracion\Domain\AccountEntryKind::cases() as $kind)@if($kind !== \Simon\ClientesAdministracion\Domain\AccountEntryKind::Opening)<option value="{{ $kind->value }}">{{ $kind->label() }}</option>@endif @endforeach
            @endif
        </x-simon::select></label>
        <label>Importe en pesos argentinos (ARS)<x-simon::input name="amount" type="text" inputmode="decimal" required maxlength="40" placeholder="1234,50" /></label>
        <p>Sin separador de miles, con hasta dos decimales. Cargos y cobros usan importes positivos; un ajuste puede ser positivo o negativo. Para conciliar, ingresá el saldo informado por la fuente.</p>
        <label>Fuente o referencia del importe<x-simon::input name="source" required maxlength="255" placeholder="Planilla, recibo o comprobante de referencia" /></label>
        <label>Detalle o motivo del ajuste<x-simon::textarea name="note" maxlength="2000"></x-simon::textarea></label>
        <x-filament::button type="submit">Registrar movimiento financiero</x-filament::button>
    </form>
    @endif
</x-filament::section>
<x-filament::section heading="Movimientos conservados" class="simon-section">
    <p>Los movimientos conservan su autor, fecha y fuente. Las correcciones se registran como ajustes con motivo.</p>
    <div class="simon-table"><table><thead><tr><th>Fecha/hora UTC</th><th>Tipo</th><th>Importe</th><th>Saldo posterior</th><th>Fuente</th><th>Detalle</th><th>Usuario</th></tr></thead><tbody>
    @forelse($report['entries']['items'] as $entry)
        <tr><td>{{ $entry['recorded_at'] }}</td><td>{{ \Simon\ClientesAdministracion\Domain\AccountEntryKind::from($entry['kind'])->label() }}</td><td>{{ $entry['kind'] === 'reconciliation' ? 'Sin cambio de saldo' : (new \Simon\ClientesAdministracion\Domain\Money((int) $entry['amount_minor'], $report['account']['currency'], (int) $report['account']['minor_scale']))->formatted() }}</td><td>{{ (new \Simon\ClientesAdministracion\Domain\Money((int) $entry['balance_after_minor'], $report['account']['currency'], (int) $report['account']['minor_scale']))->formatted() }}</td><td>{{ $entry['source'] }}</td><td>{{ $entry['note'] }}</td><td>#{{ $entry['actor_id'] }}</td></tr>
    @empty<tr><td colspan="7">No hay movimientos financieros registrados.</td></tr>@endforelse
    </tbody></table></div>
    <nav class="flex flex-wrap gap-4" aria-label="Páginas de movimientos financieros"><span>Página {{ $report['entries']['page'] }} de {{ $report['entries']['pages'] }} · {{ $report['entries']['total'] }} movimientos.</span>
        @if($report['entries']['page'] > 1)<x-filament::link :href="route('current-accounts.index', ['customer_id' => $report['customer']['id'], 'entry_page' => $report['entries']['page'] - 1])">Anterior</x-filament::link>@endif
        @if($report['entries']['page'] < $report['entries']['pages'])<x-filament::link :href="route('current-accounts.index', ['customer_id' => $report['customer']['id'], 'entry_page' => $report['entries']['page'] + 1])">Siguiente</x-filament::link>@endif
    </nav>
</x-filament::section>
@endif
@endsection
