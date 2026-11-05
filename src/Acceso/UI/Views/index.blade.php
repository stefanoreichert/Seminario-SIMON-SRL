@extends('shared::layout')
@section('title', $section === 'roles' ? 'Roles y permisos' : 'Usuarios')
@section('content')
<p>Los permisos se asignan mediante roles. Desactivar un usuario conserva su identidad en el historial.</p>
@if($section === 'roles')
<x-filament::section class="simon-section"><x-slot name="heading"><span>Crear rol</span></x-slot>
<form method="post" action="{{ route('access.role') }}">@csrf
<label>Nombre del rol<x-simon::input name="name" required maxlength="100" /></label>
<div class="simon-checks">@foreach($permissionLabels as $permission => $label)<label><x-simon::input type="checkbox" name="permissions[]" value="{{ $permission }}" /> {{ $label }}</label>@endforeach</div>
<x-filament::button type="submit">Crear rol</x-filament::button></form></x-filament::section>
@foreach($roles as $role)
<x-filament::section class="simon-section" collapsible :collapsed="true"><x-slot name="heading">Editar rol: {{ $role->name }}</x-slot>
<form method="post" action="{{ route('access.role') }}">@csrf
<x-simon::input type="hidden" name="role_id" value="{{ $role->id }}" /><label>Nombre<x-simon::input name="name" value="{{ $role->name }}" required maxlength="100" /></label>
<div class="simon-checks">@foreach($permissionLabels as $permission => $label)<label><x-simon::input type="checkbox" name="permissions[]" value="{{ $permission }}" :checked="($rolePermissions[$role->id] ?? collect())->contains('permission', $permission)" /> {{ $label }}</label>@endforeach</div>
<x-filament::button type="submit">Guardar permisos</x-filament::button></form></x-filament::section>
@endforeach
@else
<x-filament::section class="simon-section"><x-slot name="heading"><span>Crear usuario</span></x-slot>
<form method="post" action="{{ route('access.user') }}">@csrf
<div class="simon-grid"><label>Nombre<x-simon::input name="name" required maxlength="255" /></label><label>Correo<x-simon::input type="email" name="email" required maxlength="255" /></label>
<label>Contraseña<x-simon::input type="password" name="password" required minlength="12" autocomplete="new-password" /></label><label>Repetir contraseña<x-simon::input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" /></label>
<label>Identificador de vendedor<x-simon::select name="sales_identifier"><option value="">Sin identificador</option><option>S</option><option>G</option><option>F</option></x-simon::select></label></div>
<label><x-simon::input type="checkbox" name="active" value="1" checked /> Activo</label>
<div class="simon-checks">@foreach($roles as $role)<label><x-simon::input type="checkbox" name="role_ids[]" value="{{ $role->id }}" /> {{ $role->name }}</label>@endforeach</div><x-filament::button type="submit">Crear usuario</x-filament::button></form></x-filament::section>
@foreach($users as $user)
<x-filament::section class="simon-section" collapsible :collapsed="true"><x-slot name="heading">{{ $user->name }} · {{ $user->is_active ? 'Activo' : 'Inactivo' }}</x-slot>
<form method="post" action="{{ route('access.user') }}">@csrf
<x-simon::input type="hidden" name="user_id" value="{{ $user->id }}" />
<div class="simon-grid"><label>Nombre<x-simon::input name="name" value="{{ $user->name }}" required maxlength="255" /></label><label>Correo<x-simon::input type="email" name="email" value="{{ $user->email }}" required maxlength="255" /></label>
<label>Nueva contraseña (vacía conserva la actual)<x-simon::input type="password" name="password" minlength="12" autocomplete="new-password" /></label><label>Repetir nueva contraseña<x-simon::input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" /></label>
<label>Identificador<x-simon::select name="sales_identifier"><option value="">Sin identificador</option>@foreach(['S','G','F'] as $identifier)<option @selected($user->sales_identifier === $identifier)>{{ $identifier }}</option>@endforeach</x-simon::select></label></div>
<label><x-simon::input type="checkbox" name="active" value="1" :checked="$user->is_active" /> Activo</label>
<div class="simon-checks">@foreach($roles as $role)<label><x-simon::input type="checkbox" name="role_ids[]" value="{{ $role->id }}" :checked="($assignments[$user->id] ?? collect())->contains('role_id', $role->id)" /> {{ $role->name }}</label>@endforeach</div><x-filament::button type="submit">Guardar usuario y roles</x-filament::button></form></x-filament::section>
@endforeach
@endif
@endsection
