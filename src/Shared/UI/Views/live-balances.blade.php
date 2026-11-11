<div wire:poll.2s data-livewire-refresh data-refresh-url="{{ $url }}" @if($kind === 'operations') id="operations-balances" @endif tabindex="-1">
    @include($partial, $data)
</div>
