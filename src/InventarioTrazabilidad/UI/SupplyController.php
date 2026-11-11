<?php

namespace Simon\InventarioTrazabilidad\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Simon\InventarioTrazabilidad\Application\ManageSupplies;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\HandlesOperations;

final class SupplyController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, ManageSupplies $supplies, AccessPolicy $access): View
    {
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return view('inventario::supplies', ['pageTitle' => 'Insumos y vencimientos', 'report' => $supplies->overview($request->user()->id, (int) ($data['page'] ?? 1)), 'canManage' => $access->allows($request->user()->id, 'production.manage'), 'operationId' => (string) Str::uuid()]);
    }

    public function configure(Request $request, ManageSupplies $supplies): RedirectResponse
    {
        $data = $request->validate(['anticipation_days' => ['required', 'integer', 'min:0', 'max:3650']]);

        return $this->perform(fn () => $supplies->configure($request->user()->id, (int) $data['anticipation_days']), 'Anticipación de alertas guardada.');
    }

    public function store(Request $request, ManageSupplies $supplies): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:150'], 'lot' => ['required', 'string', 'max:100'], 'expires_on' => ['required', 'date_format:Y-m-d'], 'production_id' => ['nullable', 'integer', 'min:1']]);

        return $this->perform(fn (): int => $supplies->register($request->user()->id, $data['operation_id'], $data['name'], $data['lot'], $data['expires_on'], isset($data['production_id']) ? (int) $data['production_id'] : null), 'Insumo registrado; revisá su advertencia de vencimiento.');
    }
}
