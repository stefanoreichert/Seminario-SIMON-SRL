@extends('shared::layout')
@section('title', 'Clientes')
@section('content')
<p><a href="{{ route('customers.create') }}">Registrar cliente</a></p>
<form method="get" action="{{ route('customers.index') }}" role="search">
    <label>Buscar por nombre, CUIT, teléfono o correo
        <input type="search" name="q" value="{{ $query }}" maxlength="255">
    </label>
    <button>Buscar clientes</button>
    @if($query !== '')<a href="{{ route('customers.index') }}">Mostrar todos</a>@endif
</form>
<p role="status">{{ $total }} {{ $total === 1 ? 'cliente encontrado' : 'clientes encontrados' }}.</p>
<div class="operations-table">
    <table>
        <thead><tr><th scope="col">Nombre</th><th scope="col">CUIT</th><th scope="col">Teléfono</th><th scope="col">Correo</th><th scope="col">Perfil e historial</th></tr></thead>
        <tbody>
        @forelse($items as $customer)
            <tr>
                <td>{{ $customer['name'] }}</td><td>{{ $customer['cuit'] }}</td>
                <td>{{ $customer['phone'] ?? 'Sin informar' }}</td><td>{{ $customer['email'] ?? 'Sin informar' }}</td>
                <td><a href="{{ route('customers.show', $customer['id']) }}" aria-label="Ver y editar {{ $customer['name'] }}">Ver y editar</a></td>
            </tr>
        @empty
            <tr><td colspan="5">No hay clientes para esta búsqueda.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($total > $per_page)
<nav aria-label="Páginas de clientes">
    @if($page > 1)<a href="{{ route('customers.index', ['q' => $query, 'page' => $page - 1]) }}">Anterior</a>@endif
    <span>Página {{ $page }} de {{ (int) ceil($total / $per_page) }}</span>
    @if($page * $per_page < $total)<a href="{{ route('customers.index', ['q' => $query, 'page' => $page + 1]) }}">Siguiente</a>@endif
</nav>
@endif
@endsection
