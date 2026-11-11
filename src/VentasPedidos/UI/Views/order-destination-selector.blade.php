<div class="contents">
    <div>
        <label>Cliente
            <x-simon::select name="customer_id" wire:model.change.live="customer" required data-address-source>
                <option value="">Seleccioná un cliente</option>
                @if($customer !== '' && !collect($customers)->contains(fn($item) => (string)$item->id === $customer))
                    <option value="{{ $customer }}" selected>Cliente no disponible · {{ $customer }}</option>
                @endif
                @foreach($customers as $item)
                    <option value="{{ $item->id }}" @selected((string)$item->id === $customer)>{{ $item->name }} · {{ $item->cuit }}</option>
                @endforeach
            </x-simon::select>
        </label>
        @error('customer_id')<span class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
    </div>
    <div>
        <label>Dirección de entrega
            <x-simon::select name="delivery_address_id" wire:model="address" wire:loading.attr="disabled" data-addresses-ready="{{ $customer !== '' ? 'true' : 'false' }}">
                <option value="">Definir destino al despachar</option>
                @if($address !== '' && !collect($addresses)->contains(fn($item) => (string)$item['id'] === $address))
                    <option value="{{ $address }}" selected>Dirección no disponible · {{ $address }}</option>
                @endif
                @foreach($addresses as $item)
                    <option value="{{ $item['id'] }}" @selected((string)$item['id'] === $address)>{{ $item['label'] }} · {{ $item['street'] }} · {{ $item['locality'] }} / {{ $item['province'] }}</option>
                @endforeach
            </x-simon::select>
        </label>
        @error('delivery_address_id')<span class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</span>@enderror
        <p data-address-loading wire:loading role="status">Cargando direcciones…</p>
    </div>
</div>
