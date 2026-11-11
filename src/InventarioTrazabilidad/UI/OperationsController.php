<?php

namespace Simon\InventarioTrazabilidad\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Simon\Acceso\Domain\Permissions;
use Simon\ClientesAdministracion\Application\ManageCustomers;
use Simon\ClientesAdministracion\Infrastructure\SqlCustomers;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\InventarioTrazabilidad\Application\PrepareInventory;
use Simon\InventarioTrazabilidad\Application\QueryProductionNeeds;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\ReadSourceUnavailable;
use Simon\Shared\UI\HandlesOperations;
use Simon\VentasPedidos\Application\ListOrders;
use Simon\VentasPedidos\Application\PendingDemand;
use Simon\VentasPedidos\Application\RegisterOrder;
use Simon\VentasPedidos\Domain\OrderOrigin;
use Simon\VentasPedidos\UI\OrderFormData;
use Simon\VentasPedidos\UI\OrderRequest;

final class OperationsController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, AccessPolicy $access, SqlInventory $inventory, SqlCustomers $customers, ListOrders $sales, OrderFormData $orderForms, PendingDemand $demand, QueryProductionNeeds $needs): View
    {
        $permissions = [];

        foreach (array_keys(Permissions::all()) as $permission) {
            $permissions[$permission] = $access->allows($request->user()->id, $permission);
        }

        $section = $request->routeIs('operations.snapshot') ? $request->query('section', 'overview') : $request->route('section', 'overview');
        $modules = [
            'overview' => ['Resumen operativo', array_keys(array_diff_key($permissions, ['access.manage' => true]))],
            'orders' => ['Pedidos', ['orders.create', 'orders.manage', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize']],
            'catalog' => ['Catálogo', ['catalog.manage', 'inventory.view']],
            'production' => ['Producción', ['production.manage', 'inventory.view', 'inventory.receive']],
            'stock' => ['Stock', ['inventory.view', 'inventory.receive', 'inventory.opening', 'inventory.dispatch']],
            'dispatch' => ['Despachos', ['inventory.dispatch']],
            'authorization' => ['Autorizaciones de despacho', ['dispatch.authorize']],
            'audit' => ['Auditoría de movimientos', ['audit.view']],
        ];
        abort_unless(is_string($section) && isset($modules[$section]), 404);
        abort_unless(array_any($modules[$section][1], fn (string $permission): bool => $permissions[$permission]), 403);
        $withStock = $permissions['inventory.view'] || $permissions['inventory.receive'] || $permissions['inventory.dispatch'] || $permissions['production.manage'] || $permissions['inventory.opening'];
        $originFilter = null;

        if ($section === 'orders') {
            $filters = $request->validate(['origin' => ['nullable', 'string', Rule::in(array_keys(OrderOrigin::labels()))]]);
            $originFilter = $filters['origin'] ?? null;
        }

        $mayReadOrders = array_any(['orders.manage', 'orders.create', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize'], fn (string $permission): bool => $permissions[$permission]);
        $orderData = $mayReadOrders ? $sales->handle($request->user()->id, $originFilter) : ['orders' => [], 'lines' => []];
        $overview = $inventory->overview($withStock);
        $operationId = (string) Str::uuid();
        $pendingProduction = null;
        $productionNeeds = null;
        $planningUnavailable = false;

        if ($section === 'production' && ($permissions['production.manage'] || $permissions['inventory.view'])) {
            $pages = $request->validate(['article_page' => ['nullable', 'integer', 'min:1'], 'needs_page' => ['nullable', 'integer', 'min:1']]);

            try {
                $productionNeeds = $needs->handle($request->user()->id, (int) ($pages['needs_page'] ?? 1));
                $pendingProduction = $demand->forProduction($request->user()->id, (int) ($pages['article_page'] ?? 1));
            } catch (ReadSourceUnavailable $exception) {
                report($exception);
                $planningUnavailable = true;
                $productionNeeds = null;
            }
        }

        return view($request->routeIs('operations.snapshot') ? 'inventario::balances' : 'inventario::index', [
            'section' => $section, 'pageTitle' => $modules[$section][0],
            'originFilter' => $originFilter, 'originLabels' => OrderOrigin::labels(),
            'showStock' => $withStock && in_array($section, ['overview', 'stock', 'dispatch'], true),
            'showProduction' => $withStock && in_array($section, ['overview', 'production', 'stock'], true),
            'showOrders' => in_array($section, ['overview', 'orders', 'dispatch'], true),
            'withStock' => $withStock,
            ...$overview, 'permissions' => $permissions, 'customers' => $customers->customers($permissions['clients.manage']),
            ...$orderData,
            'audits' => $permissions['audit.view'] ? $inventory->recentAudit() : [],
            'operationId' => $operationId,
            'pendingProduction' => $pendingProduction,
            'productionNeeds' => $productionNeeds, 'planningUnavailable' => $planningUnavailable,
            'orderForm' => $section === 'orders' && ($permissions['orders.create'] || $permissions['orders.manage']) ? $orderForms->make($request, $operationId) : null,
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

    public function order(OrderRequest $request, RegisterOrder $orders): RedirectResponse
    {
        $data = $request->validated();
        $lines = array_map(fn (array $line): array => ['variant_id' => (int) $line['variant_id'], 'quantity' => (int) $line['quantity']], $data['lines']);

        $asDraft = ($data['intent'] ?? 'confirm') === 'draft';

        return $this->perform(fn (): int => $orders->handle($request->user()->id, $data['operation_id'], (int) $data['customer_id'], $data['origin'], $lines, isset($data['delivery_address_id']) ? (int) $data['delivery_address_id'] : null, $asDraft), $asDraft ? 'Borrador guardado. Todavía no genera pares pendientes ni admite despachos.' : 'Pedido confirmado: se guardaron todos sus artículos.', route('operations.orders', ['origin' => $data['origin']]));
    }

    public function authorizeDispatch(Request $request, ManageCustomers $customers): RedirectResponse
    {
        $data = $request->validate(['order_id' => ['required', 'integer', 'exists:business_orders,id'], 'conditions' => ['required', 'string', 'max:10000'], 'billing' => ['boolean'], 'credit' => ['boolean']]);

        return $this->perform(fn () => $customers->authorize($request->user()->id, (int) $data['order_id'], $request->boolean('billing'), $request->boolean('credit'), $data['conditions']), 'Validación administrativa guardada.', route('operations.authorization', ['pedido' => $data['order_id']]));
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
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'order_line_id' => ['required', 'integer', 'min:1'], 'batch_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'owner_withdrawal' => ['boolean'], 'delivery_address_id' => ['nullable', 'integer', 'min:1']]);

        return $this->perform(fn (): int => $movements->dispatch($request->user()->id, $data['operation_id'], (int) $data['order_line_id'], (int) $data['batch_id'], (int) $data['quantity'], $request->boolean('owner_withdrawal'), isset($data['delivery_address_id']) ? (int) $data['delivery_address_id'] : null), 'Despacho confirmado. Se actualizaron stock y pares pendientes; no se registró ningún cobro.');
    }
}
