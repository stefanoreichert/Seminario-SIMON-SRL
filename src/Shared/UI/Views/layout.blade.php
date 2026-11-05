@push('scripts')
    @vite('resources/js/app.js')
@endpush
<x-filament-panels::layout.index>
    <div class="fi-page">
        <header class="fi-header"><h1 class="fi-header-heading">@yield('title')</h1></header>
        <div class="simon-content">
            @if(session('status'))<x-filament::section icon="heroicon-o-check-circle" icon-color="success"><p role="status">{{ session('status') }}</p></x-filament::section>@endif
            @if($errors->any())<x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger" heading="Revisá los datos"><ul class="list-disc space-y-1 ps-5 text-sm" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-filament::section>@endif
            @yield('content')
        </div>
    </div>
</x-filament-panels::layout.index>
