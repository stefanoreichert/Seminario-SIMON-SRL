<?php

namespace Simon\ClientesAdministracion\UI;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\Shared\Domain\PermissionDenied;

final class CustomerSearch extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(except: 1)]
    public int $page = 1;

    public function mount(string $query = '', int $page = 1): void
    {
        $this->query = $query;
        $this->page = $page;
    }

    public function search(): void
    {
        $this->validate(['query' => ['string', 'max:255']]);
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function clear(): void
    {
        $this->query = '';
        $this->page = 1;
    }

    public function render(ManageCustomers $customers): View
    {
        abort_unless(auth()->check() && (bool) auth()->user()->fresh()?->is_active, 403);
        $this->validate(['query' => ['string', 'max:255'], 'page' => ['integer', 'min:1']]);

        try {
            $report = $customers->search((int) auth()->id(), $this->query, $this->page);
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        }

        return view('clientes::customer-search', $report);
    }
}
