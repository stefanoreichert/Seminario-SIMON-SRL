<div x-on:order-line-added.window="$nextTick(() => $el.querySelector('[data-order-line]:last-child select')?.focus())" x-on:order-line-removed.window="$nextTick(() => $el.querySelector('[data-add-line]')?.focus())">
    <div data-order-lines class="grid gap-4">
        @foreach($lines as $index => $line)
            @include('ventas::order-line', ['index' => $index, 'line' => $line, 'lineCount' => count($lines)])
        @endforeach
    </div>
    <p data-line-count role="status" aria-live="polite">{{ count($lines) }} líneas de artículos. Máximo: {{ \Simon\VentasPedidos\Domain\OrderLines::MAX_LINES }}.</p>
    <p data-lines-loading wire:loading role="status">Actualizando artículos…</p>
    <x-filament::button type="button" color="gray" icon="heroicon-o-plus" data-add-line wire:click="addLine" wire:loading.attr="disabled" :disabled="count($lines) >= \Simon\VentasPedidos\Domain\OrderLines::MAX_LINES">Agregar otro artículo</x-filament::button>
</div>
