@extends('shared::layout')
@section('title', 'Clientes')
@section('content')
<p><x-filament::link href="{{ route('customers.create') }}">Registrar cliente</x-filament::link></p>
@livewire(\Simon\ClientesAdministracion\UI\CustomerSearch::class, ['query' => $query, 'page' => $page])
@endsection
