<?php

namespace Simon\ClientesAdministracion\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\Shared\UI\HandlesOperations;

final class CustomerAddressController extends Controller
{
    use HandlesOperations;

    public function create(Request $request, int $customerId, ManageCustomers $customers): View
    {
        $customer = $customers->profile($request->user()->id, $customerId);
        abort_if($customer === null, 404);

        return view('clientes::address', ['customer' => $customer, 'address' => null]);
    }

    public function edit(Request $request, int $customerId, int $addressId, ManageCustomers $customers, ManageAddresses $addresses): View
    {
        $customer = $customers->profile($request->user()->id, $customerId);
        $address = $addresses->find($request->user()->id, $customerId, $addressId);
        abort_if($customer === null || $address === null, 404);

        return view('clientes::address', ['customer' => $customer, 'address' => $address]);
    }

    public function store(Request $request, int $customerId, ManageAddresses $addresses): RedirectResponse
    {
        return $this->save($request, $customerId, null, $addresses);
    }

    public function update(Request $request, int $customerId, int $addressId, ManageAddresses $addresses): RedirectResponse
    {
        return $this->save($request, $customerId, $addressId, $addresses);
    }

    public function options(Request $request, int $customerId, ManageAddresses $addresses): JsonResponse
    {
        return response()->json($addresses->options($request->user()->id, $customerId));
    }

    private function save(Request $request, int $customerId, ?int $addressId, ManageAddresses $addresses): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'], 'street' => ['required', 'string', 'max:255'],
            'locality' => ['required', 'string', 'max:100'], 'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'], 'country' => ['nullable', 'string', 'max:100'],
            'references' => ['nullable', 'string', 'max:1000'], 'active' => ['required', 'boolean'],
        ]);

        return $this->perform(fn (): int => $addresses->save(
            $request->user()->id, $customerId, $addressId, $data['label'], $data['street'], $data['locality'], $data['province'],
            $data['postal_code'] ?? null, $data['country'] ?? null, $data['references'] ?? null, $request->boolean('active'),
        ), 'Dirección guardada. Los destinos de pedidos y despachos anteriores se conservaron.');
    }
}
