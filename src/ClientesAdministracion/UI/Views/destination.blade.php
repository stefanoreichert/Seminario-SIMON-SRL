@if(!empty($destination))
<span><strong>{{ $destination['label'] }}</strong> · {{ $destination['street'] }} · {{ $destination['locality'] }} / {{ $destination['province'] }}
@if(!empty($destination['postal_code'])) · CP {{ $destination['postal_code'] }}@endif
@if(!empty($destination['country'])) · {{ $destination['country'] }}@endif
</span>
@if(!empty($destination['references']))<p class="whitespace-pre-wrap">{{ $destination['references'] }}</p>@endif
@else
<span>Sin destino registrado</span>
@endif
