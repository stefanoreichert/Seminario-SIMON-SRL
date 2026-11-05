@extends('shared::layout')
@section('title', $customer === null ? 'Registrar cliente' : 'Perfil del cliente')
@section('content')
<p><x-filament::link href="{{ route('customers.index') }}">Volver a clientes</x-filament::link></p>
<x-filament::section class="simon-section" aria-labelledby="commercial-profile"><x-slot name="heading"><span id="commercial-profile">Datos comerciales</span></x-slot>
    <p>El nombre y el CUIT son obligatorios. Podés completar el teléfono y el correo más adelante.</p>
    <form method="post" action="{{ $customer === null ? route('customers.store') : route('customers.update', $customer['id']) }}">
        @csrf
        @if($customer !== null)@method('PUT')@endif
        <div class="simon-grid">
            <label>Nombre comercial<x-simon::input name="name" value="{{ old('name', $customer['name'] ?? '') }}" required maxlength="255" autocomplete="organization" /></label>
            <label>CUIT único<x-simon::input name="cuit" value="{{ old('cuit', $customer['cuit'] ?? '') }}" required maxlength="32" /></label>
            <label>Teléfono<x-simon::input name="phone" type="tel" value="{{ old('phone', $customer['phone'] ?? '') }}" maxlength="50" autocomplete="tel" /></label>
            <label>Correo electrónico<x-simon::input name="email" type="email" value="{{ old('email', $customer['email'] ?? '') }}" maxlength="255" autocomplete="email" /></label>
        </div>
        @if($customer === null)
            <label>Observación crediticia inicial<x-simon::textarea name="credit_history" maxlength="10000">{{ old('credit_history') }}</x-simon::textarea></label>
        @endif
        <x-filament::button type="submit">{{ $customer === null ? 'Registrar cliente' : 'Guardar perfil comercial' }}</x-filament::button>
    </form>
</x-filament::section>
@if($customer !== null)
<x-filament::section class="simon-section" aria-labelledby="delivery-addresses"><x-slot name="heading"><span id="delivery-addresses">Direcciones de entrega</span></x-slot>
    <p><x-filament::link href="{{ route('customers.addresses.create', $customer['id']) }}">Agregar dirección de entrega</x-filament::link></p>
    <p>Las direcciones activas se pueden elegir al registrar pedidos o despachar. Cada operación conserva una copia del destino utilizado.</p>
    <div class="simon-table"><table>
        <thead><tr><th scope="col">Etiqueta</th><th scope="col">Domicilio</th><th scope="col">Localidad / provincia</th><th scope="col">Otros datos</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
        <tbody>
        @forelse($addresses as $address)
            <tr>
                <td>{{ $address['label'] }}</td><td>{{ $address['street'] }}</td>
                <td>{{ $address['locality'] }} / {{ $address['province'] }}</td>
                <td>{{ $address['postal_code'] }} {{ $address['country'] }}<p class="whitespace-pre-wrap">{{ $address['references'] }}</p></td>
                <td>{{ $address['is_active'] ? 'Activa' : 'Inactiva' }}</td>
                <td><x-filament::link href="{{ route('customers.addresses.edit', [$customer['id'], $address['id']]) }}" aria-label="Editar dirección {{ $address['label'] }}">Editar dirección</x-filament::link></td>
            </tr>
        @empty
            <tr><td colspan="6">Este cliente todavía no tiene direcciones registradas.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</x-filament::section>
<x-filament::section class="simon-section" aria-labelledby="credit-history"><x-slot name="heading"><span id="credit-history">Historial crediticio</span></x-slot>
    <p>La evaluación se carga manualmente. Cada observación conserva su autor y fecha; guardar el perfil comercial mantiene el historial.</p>
    <h3>Última observación</h3>
    <p class="whitespace-pre-wrap">{{ $customer['credit_history'] !== '' ? $customer['credit_history'] : 'Sin observaciones crediticias.' }}</p>
    <form method="post" action="{{ route('customers.credit', $customer['id']) }}">
        @csrf
        <label>Nueva observación crediticia<x-simon::textarea name="credit_history" required maxlength="10000">{{ old('credit_history') }}</x-simon::textarea></label>
        <x-filament::button type="submit">Guardar observación</x-filament::button>
    </form>
    <h3>Observaciones anteriores y actuales</h3>
    <div class="simon-table">
        <table>
            <thead><tr><th scope="col">Fecha y hora UTC</th><th scope="col">Registrada por</th><th scope="col">Observación</th></tr></thead>
            <tbody>
            @forelse($customer['credit_entries'] as $entry)
                <tr><td><time>{{ $entry['recorded_at'] }}</time></td><td>{{ $entry['actor_name'] }}</td><td class="whitespace-pre-wrap">{{ $entry['note'] !== '' ? $entry['note'] : 'Sin observación inicial.' }}</td></tr>
            @empty
                <tr><td colspan="3">No hay observaciones registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-filament::section>
@endif
@endsection
