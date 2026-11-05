@extends('shared::layout')
@section('title', $address === null ? 'Agregar dirección de entrega' : 'Editar dirección de entrega')
@section('content')
<p><x-filament::link href="{{ route('customers.show', $customer['id']) }}">Volver al perfil de {{ $customer['name'] }}</x-filament::link></p>
<x-filament::section class="simon-section"><x-slot name="heading"><span>{{ $customer['name'] }} · {{ $customer['cuit'] }}</span></x-slot>
    <p>La etiqueta permite distinguir sucursales o destinos. Editar o desactivar esta dirección conserva las copias ya fijadas en pedidos y despachos. Un pedido confirmado mantiene su destino; al despachar podés elegir otra dirección activa del mismo cliente.</p>
    <form method="post" action="{{ $address === null ? route('customers.addresses.store', $customer['id']) : route('customers.addresses.update', [$customer['id'], $address['id']]) }}">
        @csrf
        @if($address !== null)@method('PUT')@endif
        <div class="simon-grid">
            <label>Etiqueta del destino<x-simon::input name="label" value="{{ old('label', $address['label'] ?? '') }}" required maxlength="100" placeholder="Casa central, sucursal…" /></label>
            <label>Domicilio<x-simon::input name="street" value="{{ old('street', $address['street'] ?? '') }}" required maxlength="255" autocomplete="street-address" /></label>
            <label>Localidad<x-simon::input name="locality" value="{{ old('locality', $address['locality'] ?? '') }}" required maxlength="100" autocomplete="address-level2" /></label>
            <label>Provincia<x-simon::input name="province" value="{{ old('province', $address['province'] ?? '') }}" required maxlength="100" autocomplete="address-level1" /></label>
            <label>Código postal<x-simon::input name="postal_code" value="{{ old('postal_code', $address['postal_code'] ?? '') }}" maxlength="20" autocomplete="postal-code" /></label>
            <label>País<x-simon::input name="country" value="{{ old('country', $address['country'] ?? '') }}" maxlength="100" autocomplete="country-name" /></label>
        </div>
        <label>Referencias para la entrega<x-simon::textarea name="references" maxlength="1000">{{ old('references', $address['references'] ?? '') }}</x-simon::textarea></label>
        <x-simon::input type="hidden" name="active" value="0" />
        <label><x-simon::input type="checkbox" name="active" value="1" :checked="old('active', $address['is_active'] ?? true)" /> Ofrecer esta dirección para nuevas selecciones de destino</label>
        <x-filament::button type="submit">Guardar dirección</x-filament::button>
    </form>
</x-filament::section>
@endsection
