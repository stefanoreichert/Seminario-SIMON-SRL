<div class="simon-content" x-on:authorization-selected.window="$nextTick(() => document.getElementById('validacion-pedido')?.focus())">
    <p>Elegí un pedido para revisar facturación, crédito y condiciones. Las dos validaciones son necesarias para despachar.</p>

    <x-filament::section heading="Pedidos para autorizar" class="simon-section">
        <form class="simon-operation-form" method="get" action="{{ route('operations.authorization') }}" role="search" wire:submit="search">
            <label>Buscar por número de pedido o cliente
                <x-simon::input type="search" name="q" wire:model="query" value="{{ $query }}" maxlength="255" />
            </label>
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Buscar pedidos</x-filament::button>
        </form>
        <p role="status">{{ $orders['total'] }} {{ $orders['total'] === 1 ? 'pedido encontrado' : 'pedidos encontrados' }}. Solo se muestran pedidos confirmados o parciales.</p>
        <div class="simon-table simon-operation-form"><table>
            <thead><tr><th scope="col">Pedido</th><th scope="col">Cliente</th><th scope="col">Estado del pedido</th><th scope="col">Facturación</th><th scope="col">Crédito</th><th scope="col">Acción</th></tr></thead>
            <tbody>
            @forelse($orders['items'] as $order)
                <tr>
                    <td>{{ $order->number }}<br>{{ $originLabels[$order->origin] ?? $order->origin }}</td>
                    <td>{{ $order->customer_name }}</td><td>{{ $order->status }}</td>
                    <td>{{ $order->billing_validated ? 'Validada' : 'Pendiente' }}</td><td>{{ $order->credit_validated ? 'Validado' : 'Pendiente' }}</td>
                    <td><x-filament::button tag="a" color="gray" icon="heroicon-o-pencil-square" href="{{ route('operations.authorization', ['pedido' => $order->id, 'q' => $query ?: null, 'page' => $page > 1 ? $page : null]) }}" :spa-mode="false" wire:click.prevent="selectOrder({{ $order->id }})" aria-label="Revisar autorización del pedido {{ $order->number }}">Revisar</x-filament::button></td>
                </tr>
            @empty
                <tr><td colspan="6">No hay pedidos para esta búsqueda.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if($orders['pages'] > 1)
            <nav aria-label="Páginas de autorizaciones">
                @if($page > 1)<x-filament::link href="{{ route('operations.authorization', ['q' => $query ?: null, 'page' => $page - 1]) }}" :spa-mode="false" wire:click.prevent="goToPage({{ $page - 1 }})">Anterior</x-filament::link>@endif
                <span>Página {{ $page }} de {{ $orders['pages'] }}</span>
                @if($page < $orders['pages'])<x-filament::link href="{{ route('operations.authorization', ['q' => $query ?: null, 'page' => $page + 1]) }}" :spa-mode="false" wire:click.prevent="goToPage({{ $page + 1 }})">Siguiente</x-filament::link>@endif
            </nav>
        @endif
    </x-filament::section>

    @if($selected)
        <div id="validacion-pedido" tabindex="-1">
        <x-filament::section class="simon-section" :heading="'Validación del pedido '.$selected->number" wire:key="authorization-{{ $selected->id }}">
            <p><strong>Cliente:</strong> {{ $selected->customer_name }} · <strong>Estado:</strong> {{ $selected->status }} · <strong>Origen:</strong> {{ $originLabels[$selected->origin] ?? $selected->origin }}</p>
            <div class="simon-table"><table>
                <thead><tr><th scope="col">Artículo</th><th scope="col">Pedidos</th><th scope="col">Entregados</th><th scope="col">Pendientes</th></tr></thead>
                <tbody>@foreach($lines as $line)<tr><td>{{ $line->article }}</td><td>{{ $line->quantity }}</td><td>{{ $line->dispatched }}</td><td>{{ $line->pending }}</td></tr>@endforeach</tbody>
            </table></div>
            @if($selected->validated_at)
                <p>Última validación: usuario #{{ $selected->validator_id }} · {{ $selected->validated_at }}</p>
            @else
                <p>Todavía no se registró una validación para este pedido.</p>
            @endif
            <form class="simon-operation-form" method="post" action="{{ route('operations.authorize') }}">@csrf
                <x-simon::input type="hidden" name="order_id" value="{{ $selected->id }}" />
                <div class="simon-checks">
                    <label><x-simon::input type="checkbox" name="billing" value="1" :checked="(bool) $selected->billing_validated" /> Facturación validada</label>
                    <label><x-simon::input type="checkbox" name="credit" value="1" :checked="(bool) $selected->credit_validated" /> Crédito validado</label>
                </div>
                <label>Condiciones autorizadas<x-simon::textarea name="conditions" required maxlength="10000">{{ $selected->conditions }}</x-simon::textarea></label>
                <x-filament::button type="submit" icon="heroicon-o-check">Guardar validación de {{ $selected->number }}</x-filament::button>
            </form>
        </x-filament::section>
        </div>
    @elseif($selectedOrder !== '')
        <x-filament::section heading="Pedido no disponible"><p>El pedido ya no admite validaciones o no existe. Elegí otro pedido de la lista.</p></x-filament::section>
    @endif
</div>
