<div data-management-actions>
    @if($messages !== [])
        <x-filament::section heading="Revisá los datos" icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <ul role="alert" tabindex="-1" data-management-errors>
                @foreach($messages as $errors)
                    @foreach($errors as $message)<li>{{ $message }}</li>@endforeach
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</div>
