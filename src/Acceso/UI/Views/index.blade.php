@extends('shared::layout')
@section('title', 'Usuarios, roles y permisos')
@section('content')
<p>Los permisos se asignan mediante roles. Desactivar un usuario conserva su identidad en el historial.</p>
<section class="operations-card"><h2>Crear rol</h2>
<form method="post" action="{{ route('access.role') }}">@csrf
<label>Nombre del rol<input name="name" required maxlength="100"></label>
<div class="operations-checks">@foreach($permissionLabels as $permission => $label)<label><input type="checkbox" name="permissions[]" value="{{ $permission }}"> {{ $label }}</label>@endforeach</div>
<button>Crear rol</button></form></section>
@foreach($roles as $role)
<details class="operations-card"><summary>Editar rol: {{ $role->name }}</summary>
<form method="post" action="{{ route('access.role') }}">@csrf
<input type="hidden" name="role_id" value="{{ $role->id }}"><label>Nombre<input name="name" value="{{ $role->name }}" required maxlength="100"></label>
<div class="operations-checks">@foreach($permissionLabels as $permission => $label)<label><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(($rolePermissions[$role->id] ?? collect())->contains('permission', $permission))> {{ $label }}</label>@endforeach</div>
<button>Guardar permisos</button></form></details>
@endforeach
<section class="operations-card"><h2>Crear usuario</h2>
<form method="post" action="{{ route('access.user') }}">@csrf
<div class="operations-grid"><label>Nombre<input name="name" required maxlength="255"></label><label>Correo<input type="email" name="email" required maxlength="255"></label>
<label>Contraseña<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Repetir contraseña<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label>
<label>Identificador de vendedor<select name="sales_identifier"><option value="">Sin identificador</option><option>S</option><option>G</option><option>F</option></select></label></div>
<label><input type="checkbox" name="active" value="1" checked> Activo</label>
<div class="operations-checks">@foreach($roles as $role)<label><input type="checkbox" name="role_ids[]" value="{{ $role->id }}"> {{ $role->name }}</label>@endforeach</div><button>Crear usuario</button></form></section>
@foreach($users as $user)
<details class="operations-card"><summary>{{ $user->name }} · {{ $user->is_active ? 'Activo' : 'Inactivo' }}</summary>
<form method="post" action="{{ route('access.user') }}">@csrf
<input type="hidden" name="user_id" value="{{ $user->id }}">
<div class="operations-grid"><label>Nombre<input name="name" value="{{ $user->name }}" required maxlength="255"></label><label>Correo<input type="email" name="email" value="{{ $user->email }}" required maxlength="255"></label>
<label>Nueva contraseña (vacía conserva la actual)<input type="password" name="password" minlength="12" autocomplete="new-password"></label><label>Repetir nueva contraseña<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label>
<label>Identificador<select name="sales_identifier"><option value="">Sin identificador</option>@foreach(['S','G','F'] as $identifier)<option @selected($user->sales_identifier === $identifier)>{{ $identifier }}</option>@endforeach</select></label></div>
<label><input type="checkbox" name="active" value="1" @checked($user->is_active)> Activo</label>
<div class="operations-checks">@foreach($roles as $role)<label><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(($assignments[$user->id] ?? collect())->contains('role_id', $role->id))> {{ $role->name }}</label>@endforeach</div><button>Guardar usuario y roles</button></form></details>
@endforeach
@endsection
