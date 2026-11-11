<x-filament::section :heading="isset($editingOrder) ? 'Editar borrador' : 'Registrar nota de pedido'" class="simon-section">
    <p>Agregá todos los artículos y guardá la nota una sola vez. Si una línea tiene errores, se conserva el formulario para corregirla. Un borrador no genera pares pendientes hasta su confirmación.</p>
    <form class="simon-operation-form" method="post" action="{{ isset($editingOrder) ? route('orders.update', $editingOrder['id']) : route('operations.order') }}" data-order-form data-max-lines="{{ \Simon\VentasPedidos\Domain\OrderLines::MAX_LINES }}">
        @csrf
        @if(isset($editingOrder))
            @method('PUT')
            <x-simon::input type="hidden" name="form_order_id" :value="$editingOrder['id']" />
            <x-simon::input type="hidden" name="revision" :value="$orderForm['revision']" />
        @endif
        <x-simon::input type="hidden" name="operation_id" value="{{ $orderForm['token'] }}" />
        <div class="simon-grid">
            @livewire(\Simon\VentasPedidos\UI\OrderDestinationSelector::class, ['customer' => $orderForm['customer'], 'address' => $orderForm['address'], 'orderId' => $editingOrder['id'] ?? null])
            <div>
                <label>Origen
                    <x-simon::select name="origin" required>
                        @if($permissions['orders.manage'])<option value="">Seleccioná un origen</option>@endif
                        @foreach($originLabels as $code => $label)
                            @if($permissions['orders.manage'] || auth()->user()->sales_identifier === $code)
                                <option value="{{ $code }}" @selected($orderForm['origin'] === $code || (!$permissions['orders.manage'] && auth()->user()->sales_identifier === $code))>{{ $label }}</option>
                            @endif
                        @endforeach
                    </x-simon::select>
                </label>
                @error('origin')<span class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
            </div>
        </div>
        <p data-address-status role="status" aria-live="polite"></p>
        <p>El destino elegido queda guardado en la nota. Si lo dejás pendiente, deberás resolverlo antes del despacho.</p>
        @livewire(\Simon\VentasPedidos\UI\OrderLinesEditor::class, ['lines' => $orderForm['lines'], 'orderId' => $editingOrder['id'] ?? null])
        <div class="flex flex-wrap gap-3">
            @if(isset($editingOrder))
                <x-filament::button type="submit" icon="heroicon-o-check">Guardar cambios del borrador</x-filament::button>
            @else
                <x-filament::button type="submit" name="intent" value="draft" color="gray">Guardar borrador</x-filament::button>
                <x-filament::button type="submit" name="intent" value="confirm" icon="heroicon-o-check">Confirmar pedido</x-filament::button>
            @endif
        </div>
        <x-filament::link href="{{ route('operations.orders') }}">Iniciar una nota nueva</x-filament::link>
    </form>
</x-filament::section>
