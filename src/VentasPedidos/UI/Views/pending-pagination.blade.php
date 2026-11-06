<nav aria-label="Páginas de {{ $pageLabel }}" class="flex flex-wrap items-center gap-4">
    <span>{{ $pageLabel }}: página {{ $pagination['page'] }} de {{ $pagination['pages'] }} · {{ $pagination['total'] }} grupos.</span>
    @if($pagination['page'] > 1)<x-filament::link :href="route($pendingPageRoute, [...$pendingPageParams, $pageParameter => $pagination['page'] - 1])">Anterior · {{ $pageLabel }}</x-filament::link>@endif
    @if($pagination['page'] < $pagination['pages'])<x-filament::link :href="route($pendingPageRoute, [...$pendingPageParams, $pageParameter => $pagination['page'] + 1])">Siguiente · {{ $pageLabel }}</x-filament::link>@endif
</nav>
