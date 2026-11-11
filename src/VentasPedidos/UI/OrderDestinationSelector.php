<?php

namespace Simon\VentasPedidos\UI;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Simon\ClientesAdministracion\Application\CustomerDirectory;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\Shared\Domain\PermissionDenied;

final class OrderDestinationSelector extends Component
{
    use AuthorizesOrderEditor;

    public string $customer = '';

    public string $address = '';

    #[Locked]
    public ?int $orderId = null;

    public function mount(string $customer, string $address, ?int $orderId = null): void
    {
        $this->customer = $customer;
        $this->address = $address;
        $this->orderId = $orderId;
    }

    public function updatedCustomer(): void
    {
        $this->authorizeEditor();
        $this->address = '';
    }

    public function render(CustomerDirectory $customers, ManageAddresses $addresses): View
    {
        $this->authorizeEditor();
        $options = [];

        if (ctype_digit($this->customer) && (int) $this->customer > 0) {
            try {
                $options = $addresses->options((int) auth()->id(), (int) $this->customer);
            } catch (PermissionDenied $exception) {
                abort(403, $exception->getMessage());
            }
        }

        return view('ventas::order-destination-selector', ['customers' => $customers->customers(), 'addresses' => $options]);
    }
}
