<x-filament::input.wrapper :valid="!$errors->has($attributes->get('name', ''))" class="fi-fo-textarea">
    <textarea rows="4" {{ $attributes }}>{{ $slot }}</textarea>
</x-filament::input.wrapper>
