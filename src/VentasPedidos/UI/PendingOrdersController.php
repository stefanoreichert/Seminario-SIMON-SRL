<?php

namespace Simon\VentasPedidos\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Simon\Shared\Domain\PermissionDenied;
use Simon\VentasPedidos\Application\QueryPendingOrders;
use Simon\VentasPedidos\Domain\OrderOrigin;

final class PendingOrdersController extends Controller
{
    public function index(Request $request, QueryPendingOrders $orders): View
    {
        $filters = $request->validate([
            'origin' => ['nullable', 'string', Rule::in(array_keys(OrderOrigin::labels()))],
            'customer_page' => ['nullable', 'integer', 'min:1'], 'article_page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $report = $orders->handle($request->user()->id, $filters['origin'] ?? null, (int) ($filters['customer_page'] ?? 1), (int) ($filters['article_page'] ?? 1));
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        }

        $parameters = ['origin' => $filters['origin'] ?? null, 'customer_page' => $report['customers']['page'], 'article_page' => $report['articles']['page']];

        return view($request->routeIs('pending.snapshot') ? 'ventas::pending-summary' : 'ventas::pending', [
            'pendingReport' => $report, 'withCustomers' => true, 'pendingPageRoute' => 'pending.index', 'pendingPageParams' => $parameters,
            'originFilter' => $parameters['origin'], 'originLabels' => OrderOrigin::labels(),
        ]);
    }
}
