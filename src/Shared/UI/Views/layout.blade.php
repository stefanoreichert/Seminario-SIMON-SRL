<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · SIMON SRL</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="operations">
<header class="operations-header"><a href="{{ route('filament.admin.pages.dashboard') }}">SIMON SRL · Panel</a><span>{{ auth()->user()->name }}</span>
<nav aria-label="Gestión"><a href="{{ route('operations.index') }}">Operaciones</a>
@if(app(\Simon\Shared\Application\AccessPolicy::class)->allows(auth()->id(), 'clients.manage'))<a href="{{ route('customers.index') }}">Clientes</a>@endif
@if(app(\Simon\Shared\Application\AccessPolicy::class)->allows(auth()->id(), 'access.manage'))<a href="{{ route('access.index') }}">Usuarios y permisos</a>@endif
</nav></header>
<main class="operations-main"><h1>@yield('title')</h1>
@if(session('status'))<p class="operations-success" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="operations-error" role="alert"><p>Revisá los datos:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main></body></html>
