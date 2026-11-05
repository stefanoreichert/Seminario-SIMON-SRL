<x-filament::section heading="Registrar nota de pedido" class="simon-section">
    <p>Agregá todos los artículos y confirmá la nota una sola vez. Si una línea tiene errores, se conserva el formulario para corregirla.</p>
    <form class="simon-operation-form" method="post" action="{{ route('operations.order') }}" data-order-form data-address-selector data-empty-address-label="Definir destino al despachar" data-max-lines="{{ \Simon\VentasPedidos\Domain\OrderLines::MAX_LINES }}">
        @csrf
        <x-simon::input type="hidden" name="operation_id" value="{{ $orderForm['token'] }}" />
        <div class="simon-grid">
            <div>
                <label>Cliente
                    <x-simon::select name="customer_id" required data-address-source>
                        <option value="">Seleccioná un cliente</option>
                        @if($orderForm['customer'] !== '' && !collect($customers)->contains(fn($customer) => (string)$customer->id === $orderForm['customer']))
                            <option value="{{ $orderForm['customer'] }}" selected>Cliente no disponible · {{ $orderForm['customer'] }}</option>
                        @endif
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" data-address-url="{{ route('customers.addresses.options', $customer->id) }}" @selected((string)$customer->id === $orderForm['customer'])>{{ $customer->name }} · {{ $customer->cuit }}</option>
                        @endforeach
                    </x-simon::select>
                </label>
                @error('customer_id')<span class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
            </div>
            <div>
                <label>Dirección de entrega
                    <x-simon::select name="delivery_address_id" data-addresses-ready="{{ $orderForm['customer'] !== '' ? 'true' : 'false' }}">
                        <option value="">Definir destino al despachar</option>
                        @if($orderForm['address'] !== '' && !collect($orderForm['addresses'])->contains(fn($address) => (string)$address['id'] === $orderForm['address']))
                            <option value="{{ $orderForm['address'] }}" selected>Dirección no disponible · {{ $orderForm['address'] }}</option>
                        @endif
                        @foreach($orderForm['addresses'] as $address)
                            <option value="{{ $address['id'] }}" @selected((string)$address['id'] === $orderForm['address'])>{{ $address['label'] }} · {{ $address['street'] }} · {{ $address['locality'] }} / {{ $address['province'] }}</option>
                        @endforeach
                    </x-simon::select>
                </label>
                @error('delivery_address_id')<span class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
            </div>
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
        <div data-order-lines class="grid gap-4">
            @foreach($orderForm['lines'] as $index => $line)
                @include('ventas::order-line', ['index' => $index, 'line' => $line, 'lineCount' => count($orderForm['lines'])])
            @endforeach
        </div>
        <template data-order-line-template>
            @include('ventas::order-line', ['index' => '__INDEX__', 'line' => ['variant_id' => '', 'quantity' => ''], 'lineCount' => 2])
        </template>
        <p data-line-count role="status" aria-live="polite">{{ count($orderForm['lines']) }} líneas de artículos. Máximo: {{ \Simon\VentasPedidos\Domain\OrderLines::MAX_LINES }}.</p>
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" color="gray" icon="heroicon-o-plus" data-add-line :disabled="count($orderForm['lines']) >= \Simon\VentasPedidos\Domain\OrderLines::MAX_LINES">Agregar otro artículo</x-filament::button>
            <x-filament::button type="submit" icon="heroicon-o-check">Confirmar pedido</x-filament::button>
        </div>
        <x-filament::link href="{{ route('operations.orders') }}">Iniciar una nota nueva</x-filament::link>
    </form>
</x-filament::section>
