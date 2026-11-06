<?php

namespace Simon\VentasPedidos\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Simon\ClientesAdministracion\Application\CustomerDirectory;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;
use Simon\Shared\UI\HandlesOperations;
use Simon\VentasPedidos\Application\ManageOrders;
use Simon\VentasPedidos\Domain\OrderOrigin;
use Simon\VentasPedidos\Domain\OrderStatus;

final class OrderController extends Controller
{
    use HandlesOperations;

    public function show(Request $request, int $orderId, ManageOrders $orders, AccessPolicy $access, OrderFormData $forms, CustomerDirectory $customers, CatalogVariants $catalog): View
    {
        try {
            $order = $orders->view($request->user()->id, $orderId);
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        } catch (BusinessRuleViolation $exception) {
            abort(404, $exception->getMessage());
        }

        $manage = $access->allows($request->user()->id, 'orders.manage');
        $mayChange = $manage || ($access->allows($request->user()->id, 'orders.create') && $order['seller_id'] === $request->user()->id);

        return view('ventas::order', [
            'order' => $order, 'state' => OrderStatus::from($order['status']), 'mayChange' => $mayChange,
            'permissions' => ['orders.manage' => $manage], 'originLabels' => OrderOrigin::labels(),
            'customers' => $customers->customers(), 'variants' => $catalog->options(),
            'orderForm' => $forms->make($request, (string) Str::uuid(), $order),
            'confirmToken' => (string) Str::uuid(), 'cancelToken' => (string) Str::uuid(),
        ]);
    }

    public function update(OrderRequest $request, int $orderId, ManageOrders $orders): RedirectResponse
    {
        $data = $request->validated();
        $lines = array_map(fn (array $line): array => ['variant_id' => (int) $line['variant_id'], 'quantity' => (int) $line['quantity']], $data['lines']);

        return $this->perform(fn () => $orders->saveDraft(
            $request->user()->id, $data['operation_id'], $orderId, (int) $data['revision'], (int) $data['customer_id'],
            $data['origin'], $lines, isset($data['delivery_address_id']) ? (int) $data['delivery_address_id'] : null,
        ), 'Borrador actualizado.', route('orders.show', $orderId));
    }

    public function transition(Request $request, int $orderId, ManageOrders $orders): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'], 'revision' => ['required', 'integer', 'min:0'],
            'action' => ['required', 'string', Rule::in(['confirm', 'cancel'])],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:action,cancel'],
        ]);

        return $this->perform(fn () => $orders->transition(
            $request->user()->id, $data['operation_id'], $orderId, (int) $data['revision'], $data['action'], $data['reason'] ?? null,
        ), $data['action'] === 'confirm' ? 'Pedido confirmado.' : 'Saldo pendiente anulado. Las entregas realizadas se conservaron.', route('orders.show', $orderId));
    }
}
