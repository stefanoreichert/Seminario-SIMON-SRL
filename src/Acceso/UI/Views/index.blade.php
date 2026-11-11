@extends('shared::layout')
@section('title', 'Usuarios, roles y permisos')
@section('content')
<p>Los permisos se asignan mediante roles. Desactivar un usuario conserva su identidad en el historial.</p>
<nav aria-label="Gestión de acceso">
    <x-filament::link href="{{ route('access.index') }}" :aria-current="$section === 'users' ? 'page' : null">Usuarios</x-filament::link>
    <span aria-hidden="true"> · </span>
    <x-filament::link href="{{ route('access.roles') }}" :aria-current="$section === 'roles' ? 'page' : null">Roles y permisos</x-filament::link>
</nav>
@if($section === 'roles')
<p class="simon-operation-form"><x-filament::button tag="a" size="lg" icon="heroicon-o-plus" href="{{ route('access.roles', ['nuevo' => 1]) }}">Crear rol</x-filament::button></p>
<div class="simon-table simon-operation-form"><table>
<thead><tr><th scope="col">Rol</th><th scope="col">Permisos asignados</th><th scope="col">Acciones</th></tr></thead>
<tbody>
@forelse($roles as $role)
<tr><td>{{ $role->name }}</td><td>{{ ($rolePermissions[$role->id] ?? collect())->count() }}</td>
<td><x-filament::button tag="a" color="gray" size="lg" icon="heroicon-o-pencil-square" href="{{ route('access.roles', ['editar' => $role->id]) }}" aria-label="Editar rol {{ $role->name }}">Editar permisos</x-filament::button></td></tr>
@empty
<tr><td colspan="3">No hay roles.</td></tr>
@endforelse
</tbody></table></div>
@if($editingRole || $creatingUser)
@php($role = $editingRole)
<x-filament::section class="simon-section"><x-slot name="heading">{{ $role ? 'Editar rol: '.$role->name : 'Crear rol' }}</x-slot>
<form class="simon-operation-form" method="post" action="{{ route('access.role') }}">@csrf
@if($role)<x-simon::input type="hidden" name="role_id" value="{{ $role->id }}" />@endif
<label>Nombre del rol<x-simon::input name="name" :value="$role?->name" required maxlength="100" /></label>
<h3>Permisos</h3>
<div class="simon-checks">@foreach($permissionLabels as $permission => $label)<label><x-simon::input type="checkbox" name="permissions[]" value="{{ $permission }}" :checked="$role && ($rolePermissions[$role->id] ?? collect())->contains('permission', $permission)" /> {{ $label }}</label>@endforeach</div>
<x-filament::button type="submit">{{ $role ? 'Guardar permisos' : 'Crear rol' }}</x-filament::button>
<x-filament::link href="{{ route('access.roles') }}">Cancelar</x-filament::link></form></x-filament::section>
@endif
@else
<p class="simon-operation-form"><x-filament::button tag="a" size="lg" icon="heroicon-o-plus" href="{{ route('access.index', ['nuevo' => 1]) }}">Crear usuario</x-filament::button></p>
<div class="simon-table simon-operation-form">
<table>
<thead><tr><th scope="col">Nombre</th><th scope="col">Correo</th><th scope="col">Roles</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
<tbody>
@forelse($users as $user)
<tr>
<td>{{ $user->name }}</td><td>{{ $user->email }}</td>
<td>{{ $roles->whereIn('id', ($assignments[$user->id] ?? collect())->pluck('role_id'))->pluck('name')->join(', ') ?: 'Sin roles' }}</td>
<td>{{ $user->is_active ? 'Activo' : 'Inactivo' }}</td>
<td><x-filament::button tag="a" color="gray" size="lg" icon="heroicon-o-pencil-square" href="{{ route('access.index', ['editar' => $user->id]) }}" aria-label="Editar {{ $user->name }}">Editar</x-filament::button></td>
</tr>
@empty
<tr><td colspan="5">No hay usuarios.</td></tr>
@endforelse
</tbody></table></div>
@if($editingUser || $creatingUser)
@php($user = $editingUser)
<x-filament::section class="simon-section"><x-slot name="heading">{{ $user ? 'Editar usuario: '.$user->name : 'Crear usuario' }}</x-slot>
<form class="simon-operation-form" method="post" action="{{ route('access.user') }}">@csrf
@if($user)<x-simon::input type="hidden" name="user_id" value="{{ $user->id }}" />@endif
<div class="simon-grid"><label>Nombre<x-simon::input name="name" :value="$user?->name" required maxlength="255" /></label><label>Correo<x-simon::input type="email" name="email" :value="$user?->email" required maxlength="255" /></label>
<label>{{ $user ? 'Nueva contraseña (vacía conserva la actual)' : 'Contraseña' }}<x-simon::input type="password" name="password" :required="!$user" minlength="12" autocomplete="new-password" /></label><label>Repetir contraseña<x-simon::input type="password" name="password_confirmation" :required="!$user" minlength="12" autocomplete="new-password" /></label>
<label>Identificador de vendedor<x-simon::select name="sales_identifier"><option value="">Sin identificador</option>@foreach(['S','G','F'] as $identifier)<option @selected($user?->sales_identifier === $identifier)>{{ $identifier }}</option>@endforeach</x-simon::select></label></div>
<label><x-simon::input type="checkbox" name="active" value="1" :checked="$user ? $user->is_active : true" /> Usuario activo (desmarcar para deshabilitar)</label>
<h3>Roles asignados</h3>
<div class="simon-checks">@foreach($roles as $role)<label><x-simon::input type="checkbox" name="role_ids[]" value="{{ $role->id }}" :checked="$user && ($assignments[$user->id] ?? collect())->contains('role_id', $role->id)" /> {{ $role->name }}</label>@endforeach</div>
<x-filament::button type="submit">{{ $user ? 'Guardar usuario y roles' : 'Crear usuario' }}</x-filament::button>
<x-filament::link href="{{ route('access.index') }}">Cancelar</x-filament::link></form></x-filament::section>
@endif
@endif
@endsection
