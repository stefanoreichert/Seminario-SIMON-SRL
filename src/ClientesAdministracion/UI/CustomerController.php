<?php

namespace Simon\ClientesAdministracion\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\Shared\UI\HandlesOperations;

final class CustomerController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, ManageCustomers $customers): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $data['q'] ?? '';

        return view('clientes::index', [
            'query' => $query,
            ...$customers->search($request->user()->id, $query, (int) ($data['page'] ?? 1)),
        ]);
    }

    public function create(): View
    {
        return view('clientes::form', ['customer' => null]);
    }

    public function show(Request $request, int $customerId, ManageCustomers $customers, ManageAddresses $addresses): View
    {
        $customer = $customers->profile($request->user()->id, $customerId);
        abort_if($customer === null, 404);

        return view('clientes::form', ['customer' => $customer, 'addresses' => $addresses->all($request->user()->id, $customerId)]);
    }

    public function store(Request $request, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate([
            ...$this->profileRules(), 'credit_history' => ['nullable', 'string', 'max:10000'],
        ]);

        return $this->perform(fn (): int => $customers->register(
            $request->user()->id, $data['name'], $data['cuit'], $data['credit_history'] ?? '', $data['phone'] ?? null, $data['email'] ?? null,
        ), 'Cliente registrado. Podés consultarlo en el listado de clientes.');
    }

    public function update(Request $request, int $customerId, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate($this->profileRules());

        return $this->perform(fn () => $customers->update(
            $request->user()->id, $customerId, $data['name'], $data['cuit'], $data['phone'] ?? null, $data['email'] ?? null,
        ), 'Perfil comercial actualizado.');
    }

    public function credit(Request $request, int $customerId, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate(['credit_history' => ['required', 'string', 'max:10000']]);

        return $this->perform(fn () => $customers->updateCredit(
            $request->user()->id, $customerId, $data['credit_history'],
        ), 'Observación crediticia guardada. Se conservaron las anteriores.');
    }

    /** @return array<string, list<string>> */
    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'cuit' => ['required', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email:filter', 'max:255'],
        ];
    }
}
