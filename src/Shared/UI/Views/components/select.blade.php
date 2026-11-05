@props(['errorKey' => null])
<x-filament::input.wrapper :valid="!$errors->has($errorKey ?? $attributes->get('name', ''))">
    <x-filament::input.select {{ $attributes }}>{{ $slot }}</x-filament::input.select>
</x-filament::input.wrapper>
