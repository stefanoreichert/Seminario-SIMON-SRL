<?php

namespace Simon\InventarioTrazabilidad\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Simon\Acceso\Domain\Permissions;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\ClientesAdministracion\Infrastructure\SqlCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\HandlesOperations;
use Simon\VentasPedidos\Application\RegisterOrder;
use Simon\VentasPedidos\Infrastructure\SqlOrderRegistration;

final class OperationsController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, AccessPolicy $access, SqlInventory $inventory, SqlCustomers $customers, SqlOrderRegistration $sales): View
    {
        $permissions = [];

        foreach (array_keys(Permissions::all()) as $permission) {
            $permissions[$permission] = $access->allows($request->user()->id, $permission);
        }

        abort_unless(in_array(true, array_diff_key($permissions, ['access.manage' => true]), true), 403);
        $withStock = $permissions['inventory.view'] || $permissions['inventory.receive'] || $permissions['inventory.dispatch'] || $permissions['production.manage'] || $permissions['inventory.opening'];
        $allOrders = $permissions['orders.manage'] || $permissions['inventory.view'] || $permissions['inventory.dispatch'] || $permissions['dispatch.authorize'];
        $orders = ($allOrders || $permissions['orders.create']) ? $sales->orders($allOrders ? null : $request->user()->id) : [];
        $overview = $inventory->overview($withStock);

        return view($request->routeIs('operations.snapshot') ? 'inventario::balances' : 'inventario::index', [
            'withStock' => $withStock,
            ...$overview, 'permissions' => $permissions, 'customers' => $customers->customers($permissions['clients.manage']),
            'orders' => $orders, 'lines' => $sales->lines(array_map(fn (object $order): int => $order->id, $orders)),
            'audits' => $permissions['audit.view'] ? $inventory->recentAudit() : [],
            'operationId' => (string) Str::uuid(),
        ]);
    }

    public function customer(Request $request, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'cuit' => ['required', 'string', 'max:32'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email:filter', 'max:255'], 'credit_history' => ['nullable', 'string', 'max:10000']]);

        return $this->perform(fn (): int => $customers->register($request->user()->id, $data['name'], $data['cuit'], $data['credit_history'] ?? '', $data['phone'] ?? null, $data['email'] ?? null), 'Cliente registrado.');
    }

    public function credit(Request $request, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'integer', 'min:1'], 'credit_history' => ['required', 'string', 'max:10000']]);

        return $this->perform(fn () => $customers->updateCredit($request->user()->id, (int) $data['customer_id'], $data['credit_history']), 'Historial crediticio actualizado.');
    }

    public function variant(Request $request, PrepareInventory $inventory): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64'], 'model' => ['required', 'string', 'max:100'], 'color' => ['required', 'string', 'max:100'], 'size' => ['required', 'string', 'max:30']]);

        return $this->perform(fn (): int => $inventory->variant($request->user()->id, $data['code'], $data['model'], $data['color'], $data['size']), 'Artículo registrado.');
    }

    public function order(Request $request, RegisterOrder $orders): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'], 'customer_id' => ['required', 'integer', 'min:1'], 'origin' => ['required', 'in:S,G,F'],
            'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.variant_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);
        $lines = array_map(fn (array $line): array => ['variant_id' => (int) $line['variant_id'], 'quantity' => (int) $line['quantity']], $data['lines']);

        return $this->perform(fn (): int => $orders->handle($request->user()->id, $data['operation_id'], (int) $data['customer_id'], $data['origin'], $lines), 'Pedido confirmado y numerado.');
    }

    public function authorizeDispatch(Request $request, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate(['order_id' => ['required', 'integer', 'exists:business_orders,id'], 'conditions' => ['required', 'string', 'max:10000'], 'billing' => ['boolean'], 'credit' => ['boolean']]);

        return $this->perform(fn () => $customers->authorize($request->user()->id, (int) $data['order_id'], $request->boolean('billing'), $request->boolean('credit'), $data['conditions']), 'Validación administrativa guardada.');
    }

    public function production(Request $request, PrepareInventory $inventory): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'variant_id' => ['required', 'integer', 'min:1'], 'lot' => ['required', 'string', 'max:100'], 'manufactured_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000']]);

        return $this->perform(fn (): int => $inventory->production($request->user()->id, $data['operation_id'], (int) $data['variant_id'], $data['lot'], $data['manufactured_on'], (int) $data['quantity']), 'Tanda en proceso registrada.');
    }

    public function receive(Request $request, MoveStock $movements): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'production_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000']]);

        return $this->perform(fn (): int => $movements->receive($request->user()->id, $data['operation_id'], (int) $data['production_id'], (int) $data['quantity']), 'Alta confirmada: los pares ingresaron a depósito y dejaron de estar en proceso.');
    }

    public function opening(Request $request, MoveStock $movements): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'variant_id' => ['required', 'integer', 'min:1'], 'lot' => ['required', 'string', 'max:100'], 'manufactured_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000']]);

        return $this->perform(fn (): int => $movements->opening($request->user()->id, $data['operation_id'], (int) $data['variant_id'], $data['lot'], $data['manufactured_on'], (int) $data['quantity']), 'Existencias iniciales registradas con auditoría.');
    }

    public function dispatch(Request $request, MoveStock $movements): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'order_line_id' => ['required', 'integer', 'min:1'], 'batch_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'owner_withdrawal' => ['boolean']]);

        return $this->perform(fn (): int => $movements->dispatch($request->user()->id, $data['operation_id'], (int) $data['order_line_id'], (int) $data['batch_id'], (int) $data['quantity'], $request->boolean('owner_withdrawal')), 'Despacho confirmado. Se actualizaron stock y pares pendientes; no se registró ningún cobro.');
    }
}
