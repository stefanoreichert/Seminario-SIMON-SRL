@extends('shared::layout')
@section('title', $customer === null ? 'Registrar cliente' : 'Perfil del cliente')
@section('content')
<p><a href="{{ route('customers.index') }}">Volver a clientes</a></p>
<section class="operations-card" aria-labelledby="commercial-profile">
    <h2 id="commercial-profile">Datos comerciales</h2>
    <p>El nombre y el CUIT son obligatorios. Podés completar el teléfono y el correo más adelante.</p>
    <form method="post" action="{{ $customer === null ? route('customers.store') : route('customers.update', $customer['id']) }}">
        @csrf
        @if($customer !== null)@method('PUT')@endif
        <div class="operations-grid">
            <label>Nombre comercial<input name="name" value="{{ old('name', $customer['name'] ?? '') }}" required maxlength="255" autocomplete="organization"></label>
            <label>CUIT único<input name="cuit" value="{{ old('cuit', $customer['cuit'] ?? '') }}" required maxlength="32"></label>
            <label>Teléfono<input name="phone" type="tel" value="{{ old('phone', $customer['phone'] ?? '') }}" maxlength="50" autocomplete="tel"></label>
            <label>Correo electrónico<input name="email" type="email" value="{{ old('email', $customer['email'] ?? '') }}" maxlength="255" autocomplete="email"></label>
        </div>
        @if($customer === null)
            <label>Observación crediticia inicial<textarea name="credit_history" maxlength="10000">{{ old('credit_history') }}</textarea></label>
        @endif
        <button>{{ $customer === null ? 'Registrar cliente' : 'Guardar perfil comercial' }}</button>
    </form>
</section>
@if($customer !== null)
<section class="operations-card" aria-labelledby="credit-history">
    <h2 id="credit-history">Historial crediticio</h2>
    <p>La evaluación se carga manualmente. Cada observación conserva su autor y fecha; guardar el perfil comercial mantiene el historial.</p>
    <h3>Última observación</h3>
    <p class="whitespace-pre-wrap">{{ $customer['credit_history'] !== '' ? $customer['credit_history'] : 'Sin observaciones crediticias.' }}</p>
    <form method="post" action="{{ route('customers.credit', $customer['id']) }}">
        @csrf
        <label>Nueva observación crediticia<textarea name="credit_history" required maxlength="10000">{{ old('credit_history') }}</textarea></label>
        <button>Guardar observación</button>
    </form>
    <h3>Observaciones anteriores y actuales</h3>
    <div class="operations-table">
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
</section>
@endif
@endsection
