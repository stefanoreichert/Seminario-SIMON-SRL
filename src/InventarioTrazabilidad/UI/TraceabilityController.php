<?php

namespace Simon\InventarioTrazabilidad\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simon\InventarioTrazabilidad\Application\SearchTraceability;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;

final class TraceabilityController extends Controller
{
    public function index(Request $request, SearchTraceability $traceability): View|Response
    {
        $filters = $request->validate([
            'lot' => ['nullable', 'string', 'max:100'], 'manufactured_from' => ['nullable', 'date_format:Y-m-d'],
            'manufactured_to' => ['nullable', 'date_format:Y-m-d'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = ['lot' => isset($filters['lot']) ? trim($filters['lot']) : null, 'manufactured_from' => $filters['manufactured_from'] ?? null, 'manufactured_to' => $filters['manufactured_to'] ?? null];
        $searched = array_any($search, fn (?string $value): bool => $value !== null && $value !== '');
        $error = null;
        $result = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

        try {
            $result = $traceability->handle($request->user()->id, $search['lot'], $search['manufactured_from'], $search['manufactured_to'], (int) ($filters['page'] ?? 1));
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        } catch (BusinessRuleViolation $exception) {
            $error = $exception->getMessage();
        }

        $data = ['search' => $search, 'searched' => $searched, 'result' => $result, 'searchError' => $error];

        return $error === null ? view('inventario::traceability', $data) : response()->view('inventario::traceability', $data, 422);
    }
}
