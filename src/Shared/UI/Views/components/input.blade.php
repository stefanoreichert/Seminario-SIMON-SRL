@props(['type' => 'text', 'errorKey' => null])
@if($type === 'hidden')
<input type="hidden" {{ $attributes }}>
@elseif($type === 'checkbox')
<x-filament::input.checkbox {{ $attributes }} />
@else
<x-filament::input.wrapper :valid="!$errors->has($errorKey ?? $attributes->get('name', ''))">
    <x-filament::input :type="$type" {{ $attributes }} />
</x-filament::input.wrapper>
@endif
