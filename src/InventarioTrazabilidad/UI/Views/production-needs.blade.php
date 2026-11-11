<x-filament::section heading="Necesidades de producción" class="simon-section" data-production-needs>
    <p>Stock disponible en depósito + Producción en proceso − Pedidos pendientes = Saldo proyectado, en pares por artículo.</p>
    <p>Disponible es el stock físico antes de restar pedidos. La necesidad de inyección indica cuántos pares adicionales faltan para cubrir un saldo negativo. Consultar no modifica existencias.</p>
    @if($planningUnavailable)
        <p role="alert" data-production-unavailable><strong>No disponible.</strong> No se pudo obtener una de las fuentes. No se calcula un saldo ni se reemplazan los datos faltantes por cero. La consulta se reintentará automáticamente.</p>
    @else
        <div class="grid gap-4 md:hidden">
            @forelse($productionNeeds['items'] as $row)
                <x-filament::section :heading="$row['code']" compact data-production-mobile-id="{{ $row['id'] }}">
                    <p>{{ $row['model'] }} · {{ $row['color'] }} · Talle {{ $row['size'] }}</p>
                    <dl class="grid grid-cols-3 gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <div><dt>Disponible</dt><dd>{{ $row['available'] }}</dd></div>
                        <div><dt>En proceso</dt><dd>{{ $row['in_process'] }}</dd></div>
                        <div><dt>Pendientes</dt><dd>{{ $row['pending'] }}</dd></div>
                    </dl>
                    <p>Saldo proyectado: <x-filament::badge :color="$row['balance'] < 0 ? 'danger' : 'success'">{{ $row['balance'] }} pares</x-filament::badge></p>
                    <p>Necesidad de inyección: <strong>{{ $row['injection_needed'] }} pares</strong></p>
                </x-filament::section>
            @empty
                <p>Todavía no hay artículos registrados para calcular necesidades.</p>
            @endforelse
        </div>
        <div class="simon-table hidden md:block"><table>
            <thead><tr><th>Código</th><th>Modelo</th><th>Color</th><th>Talle</th><th>Disponible</th><th>En proceso</th><th>Pendientes</th><th>Saldo proyectado</th><th>Necesidad de inyección</th></tr></thead>
            <tbody>
            @forelse($productionNeeds['items'] as $row)
                <tr data-production-variant-id="{{ $row['id'] }}" data-projected-balance="{{ $row['balance'] }}" data-injection-needed="{{ $row['injection_needed'] }}">
                    <td>{{ $row['code'] }}</td><td>{{ $row['model'] }}</td><td>{{ $row['color'] }}</td><td>{{ $row['size'] }}</td>
                    <td>{{ $row['available'] }}</td><td>{{ $row['in_process'] }}</td><td>{{ $row['pending'] }}</td>
                    <td><x-filament::badge :color="$row['balance'] < 0 ? 'danger' : 'success'">{{ $row['balance'] }}</x-filament::badge></td><td>{{ $row['injection_needed'] }} pares</td>
                </tr>
            @empty
                <tr><td colspan="9">Todavía no hay artículos registrados para calcular necesidades.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @include('ventas::pending-pagination', ['pagination' => $productionNeeds, 'pageParameter' => 'needs_page', 'pageLabel' => 'Necesidades', 'pendingPageRoute' => 'operations.production.index', 'pendingPageParams' => ['article_page' => $pendingProduction['articles']['page'] ?? null]])
        <p>Incluye todos los artículos del catálogo, con 25 por página. Los saldos se calculan por artículo; el excedente de un talle no cubre el faltante de otro.</p>
    @endif
</x-filament::section>
