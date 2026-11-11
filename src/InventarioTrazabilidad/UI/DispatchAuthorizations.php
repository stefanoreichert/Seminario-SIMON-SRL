<?php

namespace Simon\InventarioTrazabilidad\UI;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Simon\ClientesAdministracion\Application\QueryDispatchAuthorizations;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Domain\OrderOrigin;

final class DispatchAuthorizations extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(except: 1)]
    public int $page = 1;

    #[Url(as: 'pedido', except: '')]
    public string $selectedOrder = '';

    public function search(): void
    {
        $this->validate(['query' => ['string', 'max:255']]);
        $this->page = 1;
        $this->selectedOrder = '';
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->selectedOrder = '';
    }

    public function selectOrder(int $orderId): void
    {
        abort_if($orderId < 1, 404);
        $this->selectedOrder = (string) $orderId;
        $this->dispatch('authorization-selected');
    }

    public function render(QueryDispatchAuthorizations $authorizations): View
    {
        abort_unless(auth()->check() && (bool) auth()->user()->fresh()?->is_active, 403);
        $this->validate(['query' => ['string', 'max:255'], 'page' => ['integer', 'min:1'], 'selectedOrder' => ['string', 'max:20']]);
        abort_if($this->selectedOrder !== '' && (! ctype_digit($this->selectedOrder) || (int) $this->selectedOrder < 1), 404);
        try {
            $report = $authorizations->handle((int) auth()->id(), $this->query, $this->page, $this->selectedOrder === '' ? null : (int) $this->selectedOrder);
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        }
        $this->page = $report['orders']['page'];

        return view('inventario::dispatch-authorizations', [...$report, 'originLabels' => OrderOrigin::labels()]);
    }
}
