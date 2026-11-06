<div data-pending-report data-pending-pairs="{{ $pendingReport['totals']['pairs'] }}" class="grid gap-6">
<x-filament::section heading="Pedidos por atender" class="simon-section">
    <p><strong data-pending-total>{{ $pendingReport['totals']['pairs'] }} pares por entregar</strong> · {{ $pendingReport['totals']['orders'] }} notas con saldo · {{ $pendingReport['totals']['articles'] }} artículos.</p>
    <p>Los totales incluyen todas las páginas de esta consulta. La deuda monetaria se gestiona por separado.</p>
</x-filament::section>
@if($withCustomers)
<x-filament::section heading="Pendientes por cliente y artículo" class="simon-section">
    <div class="simon-table"><table><thead><tr><th>Cliente</th><th>CUIT</th><th>Código</th><th>Modelo</th><th>Color</th><th>Talle</th><th>Notas con saldo</th><th>Pares por entregar</th></tr></thead><tbody>
    @forelse($pendingReport['customers']['items'] as $row)
        <tr data-pending-customer-id="{{ $row['customer_id'] }}" data-pending-variant-id="{{ $row['variant_id'] }}"><td>{{ $row['customer']['name'] ?? 'Cliente #'.$row['customer_id'] }}</td><td>{{ $row['customer']['cuit'] ?? 'No disponible' }}</td><td>{{ $row['article']['code'] ?? '#'.$row['variant_id'] }}</td><td>{{ $row['article']['model'] ?? 'No disponible' }}</td><td>{{ $row['article']['color'] ?? 'No disponible' }}</td><td>{{ $row['article']['size'] ?? 'No disponible' }}</td><td>{{ $row['orders'] }}</td><td>{{ $row['pairs'] }}</td></tr>
    @empty
        <tr><td colspan="8">No hay mercadería pendiente para esta consulta.</td></tr>
    @endforelse
    </tbody></table></div>
    @include('ventas::pending-pagination', ['pagination' => $pendingReport['customers'], 'pageParameter' => 'customer_page', 'pageLabel' => 'Cliente y artículo'])
</x-filament::section>
@endif
<x-filament::section heading="Demanda pendiente por artículo" class="simon-section">
    <p>Pares por entregar agrupados por modelo, color y talle. Para planificar la fabricación, revisá también las existencias y las tandas en proceso.</p>
    <div class="simon-table"><table><thead><tr><th>Código</th><th>Modelo</th><th>Color</th><th>Talle</th><th>Notas con saldo</th><th>Pares por entregar</th></tr></thead><tbody>
    @forelse($pendingReport['articles']['items'] as $row)
        <tr data-pending-variant-id="{{ $row['variant_id'] }}"><td>{{ $row['article']['code'] ?? '#'.$row['variant_id'] }}</td><td>{{ $row['article']['model'] ?? 'No disponible' }}</td><td>{{ $row['article']['color'] ?? 'No disponible' }}</td><td>{{ $row['article']['size'] ?? 'No disponible' }}</td><td>{{ $row['orders'] }}</td><td>{{ $row['pairs'] }}</td></tr>
    @empty
        <tr><td colspan="6">No hay demanda pendiente para esta consulta.</td></tr>
    @endforelse
    </tbody></table></div>
    @include('ventas::pending-pagination', ['pagination' => $pendingReport['articles'], 'pageParameter' => 'article_page', 'pageLabel' => 'Artículos'])
</x-filament::section>
<p>Actualizado {{ now()->format('H:i:s') }}.</p>
</div>
