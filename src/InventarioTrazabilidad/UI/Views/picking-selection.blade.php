<div x-on:picking-ready.window="$nextTick(() => $el.querySelector('input[name=code]')?.focus())">
    <x-filament::section heading="Seleccionar línea y consultar códigos" class="simon-section">
        <form class="simon-operation-form" method="get" action="{{ route('picking.index') }}" wire:submit="search" data-picking-filter>
            <label>Línea del pedido<x-simon::input type="number" name="line_id" wire:model="lineInput" min="1" :value="$lineId" /></label>
            <label>Filtrar por registro del lote<x-simon::input type="number" name="batch_id" wire:model="batchInput" min="1" :value="$batchId" /></label>
            @error('lineInput')<p role="alert">{{ $message }}</p>@enderror
            @error('batchInput')<p role="alert">{{ $message }}</p>@enderror
            <x-filament::button type="submit" wire:loading.attr="disabled">Consultar</x-filament::button>
        </form>
        <x-filament::link :href="route('operations.dispatch.index')">Consultar líneas y destinos en Despachos</x-filament::link>
    </x-filament::section>
    @if($canDispatch && $lineId !== null)
    <x-filament::section heading="Leer pares para la línea #{{ $lineId }}" class="simon-section">
        <p>Leé el código con un lector que ingrese texto y Enter, o ingresalo en el campo. Una lectura repetida avisa el error y no agrega otro par.</p>
        <form class="simon-operation-form" wire:submit="scan" data-pair-scan-form>
            <label>Código del par<x-simon::input name="code" wire:model="code" type="text" required maxlength="100" autofocus autocomplete="off" spellcheck="false" data-pair-code /></label>
            @error('code')<p role="alert">{{ $message }}</p>@enderror
            <p role="status" aria-live="polite">{{ $status }}</p>
            <x-filament::button type="submit" wire:loading.attr="disabled">Agregar par al picking</x-filament::button>
        </form>
    </x-filament::section>
    <x-filament::section heading="Pares seleccionados" class="simon-section">
        @forelse(collect($report['selected'])->groupBy('batch_id') as $selectedBatch => $units)
            <h3>Lote de depósito #{{ $selectedBatch }} · {{ count($units) }} pares</h3>
            @foreach($units as $unit)
                <div wire:key="picked-{{ $unit['code'] }}" class="simon-operation-form">
                    <span class="break-all text-sm" data-picked-code="{{ $unit['code'] }}">{{ $unit['code'] }}</span>
                    <x-filament::button type="button" color="gray" wire:click="release({{ \Illuminate\Support\Js::from($unit['code']) }})" wire:loading.attr="disabled">Quitar de la selección</x-filament::button>
                </div>
            @endforeach
            <form class="simon-operation-form" method="post" action="{{ route('picking.dispatch') }}">@csrf
                <x-simon::input type="hidden" name="operation_id" :value="(string) \Illuminate\Support\Str::uuid()" /><x-simon::input type="hidden" name="line_id" :value="$lineId" /><x-simon::input type="hidden" name="batch_id" :value="$selectedBatch" />
                @foreach($units as $unit)<x-simon::input type="hidden" name="codes[]" :value="$unit['code']" />@endforeach
                <label>Otra dirección de entrega del cliente (opcional)<x-simon::input type="number" name="delivery_address_id" min="1" /></label>
                <label><x-simon::input type="checkbox" name="owner_withdrawal" value="1" /> Retiro de propietarios</label>
                <p>Se usa el destino guardado en el pedido salvo que elijas otro. La salida requiere validación administrativa.</p>
                <x-filament::button type="submit">Confirmar salida de {{ count($units) }} pares seleccionados</x-filament::button>
            </form>
        @empty
            <p>No hay pares seleccionados para esta línea.</p>
        @endforelse
    </x-filament::section>
    @endif
    <x-filament::section heading="Códigos registrados" class="simon-section">
        <div class="simon-table"><table><thead><tr><th>Código individual</th><th>Artículo</th><th>Lote / fabricación</th><th>Registro</th><th>Estado</th><th>Línea</th></tr></thead><tbody>
        @forelse($report['items'] as $unit)
            <tr data-pair-unit="{{ $unit['code'] }}"><td class="break-all">{{ $unit['code'] }}</td><td>{{ $unit['article_code'] }} · {{ $unit['model'] }} / {{ $unit['color'] }} / {{ $unit['size'] }}</td><td>{{ $unit['lot'] }} · {{ $unit['manufactured_on'] }}</td><td>#{{ $unit['batch_id'] }}</td><td>{{ ['available' => 'Disponible', 'picked' => 'Seleccionado', 'dispatched' => 'Despachado'][$unit['state']] }}</td><td>{{ $unit['order_line_id'] ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="6">No hay pares identificados en esta consulta.</td></tr>
        @endforelse
        </tbody></table></div>
        <nav aria-label="Páginas de códigos" class="flex flex-wrap gap-4">
            <span>Página {{ $report['page'] }} de {{ $report['pages'] }} · {{ $report['total'] }} pares.</span>
            @if($report['page'] > 1)<x-filament::link :href="route('picking.index', ['line_id' => $lineId, 'batch_id' => $batchId, 'page' => $report['page'] - 1])" :spa-mode="false" wire:click.prevent="goToPage({{ $report['page'] - 1 }})">Anterior</x-filament::link>@endif
            @if($report['page'] < $report['pages'])<x-filament::link :href="route('picking.index', ['line_id' => $lineId, 'batch_id' => $batchId, 'page' => $report['page'] + 1])" :spa-mode="false" wire:click.prevent="goToPage({{ $report['page'] + 1 }})">Siguiente</x-filament::link>@endif
        </nav>
    </x-filament::section>
</div>
