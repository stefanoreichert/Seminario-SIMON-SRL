@php
    $variantError = 'lines.'.$index.'.variant_id';
    $quantityError = 'lines.'.$index.'.quantity';
@endphp
<div data-order-line class="grid min-w-0 gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3>Artículo <span data-line-number>{{ is_int($index) ? $index + 1 : '' }}</span></h3>
        <x-filament::button type="button" color="gray" icon="heroicon-o-trash" data-remove-line :disabled="$lineCount <= 1">Quitar artículo</x-filament::button>
    </div>
    <div class="simon-grid">
        <div>
            <label for="order-line-{{ $index }}-variant">Modelo, color y talle</label>
            <x-simon::select id="order-line-{{ $index }}-variant" name="lines[{{ $index }}][variant_id]" :error-key="$variantError" aria-describedby="order-line-{{ $index }}-variant-error" required>
                <option value="">Seleccioná un artículo</option>
                @if($line['variant_id'] !== '' && !collect($variants)->contains(fn($variant) => (string)$variant->id === $line['variant_id']))
                    <option value="{{ $line['variant_id'] }}" selected>Artículo no disponible · {{ $line['variant_id'] }}</option>
                @endif
                @foreach($variants as $variant)
                    <option value="{{ $variant->id }}" @selected((string)$variant->id === $line['variant_id'])>{{ $variant->code }} · {{ $variant->model }} / {{ $variant->color }} / {{ $variant->size }}</option>
                @endforeach
            </x-simon::select>
            @error($variantError)<span data-line-error id="order-line-{{ $index }}-variant-error" class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
        </div>
        <div>
            <label for="order-line-{{ $index }}-quantity">Cantidad de pares</label>
            <x-simon::input id="order-line-{{ $index }}-quantity" name="lines[{{ $index }}][quantity]" type="number" value="{{ $line['quantity'] }}" :error-key="$quantityError" aria-describedby="order-line-{{ $index }}-quantity-error" required min="1" max="{{ \Simon\VentasPedidos\Domain\OrderLines::MAX_QUANTITY }}" step="1" />
            @error($quantityError)<span data-line-error id="order-line-{{ $index }}-quantity-error" class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
        </div>
    </div>
</div>
