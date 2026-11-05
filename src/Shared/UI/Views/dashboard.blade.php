<x-filament-panels::page>
    <x-filament::section heading="Gestión de SIMON SRL" description="Accedé a los módulos desde el menú lateral o elegí una de estas opciones.">
        <div class="grid gap-6 lg:grid-cols-3">
            @foreach(filament()->getNavigation() as $group)
                @if($group->getLabel())
                <x-filament::section :heading="$group->getLabel()">
                    <div class="grid gap-3">
                        @foreach($group->getItems() as $item)
                            <x-filament::button tag="a" :href="$item->getUrl()" :icon="$item->getIcon()" color="gray">{{ $item->getLabel() }}</x-filament::button>
                        @endforeach
                    </div>
                </x-filament::section>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
