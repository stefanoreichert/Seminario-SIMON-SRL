<?php

namespace Simon\InventarioTrazabilidad\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\InventarioTrazabilidad\Application\MoveStock;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\HandlesOperations;

final class PickingController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, ManagePicking $picking, AccessPolicy $access): View
    {
        $data = $request->validate(['line_id' => ['nullable', 'integer', 'min:1'], 'batch_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        $lineId = isset($data['line_id']) ? (int) $data['line_id'] : null;
        $batchId = isset($data['batch_id']) ? (int) $data['batch_id'] : null;

        return view('inventario::picking', [
            'pageTitle' => 'Identificación y picking', 'lineId' => $lineId, 'batchId' => $batchId,
            'report' => $picking->overview($request->user()->id, $lineId, $batchId, (int) ($data['page'] ?? 1)),
            'canIdentify' => $access->allows($request->user()->id, 'inventory.receive'),
            'canDispatch' => $access->allows($request->user()->id, 'inventory.dispatch'),
            'operationId' => (string) Str::uuid(),
        ]);
    }

    public function identify(Request $request, ManagePicking $picking): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'batch_id' => ['required', 'integer', 'min:1']]);

        return $this->perform(fn (): int => $picking->identify($request->user()->id, $data['operation_id'], (int) $data['batch_id']), 'Se asignó un código distinto a cada par físico del lote.');
    }

    public function scan(Request $request, ManagePicking $picking): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'line_id' => ['required', 'integer', 'min:1'], 'code' => ['required', 'string', 'max:100'], 'release' => ['boolean']]);

        return $this->perform(fn (): int => $picking->scan($request->user()->id, $data['operation_id'], (int) $data['line_id'], $data['code'], $request->boolean('release')), $request->boolean('release') ? 'Par retirado de la selección.' : 'Par leído y seleccionado. Podés leer el siguiente.');
    }

    public function dispatch(Request $request, MoveStock $movements): RedirectResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'], 'line_id' => ['required', 'integer', 'min:1'], 'batch_id' => ['required', 'integer', 'min:1'],
            'codes' => ['required', 'array', 'min:1', 'max:10000'], 'codes.*' => ['required', 'string', 'max:100', 'distinct:strict'],
            'owner_withdrawal' => ['boolean'], 'delivery_address_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->perform(fn (): int => $movements->dispatch($request->user()->id, $data['operation_id'], (int) $data['line_id'], (int) $data['batch_id'], count($data['codes']), $request->boolean('owner_withdrawal'), isset($data['delivery_address_id']) ? (int) $data['delivery_address_id'] : null, array_values($data['codes'])), 'Salida confirmada con los códigos individuales y la auditoría del despacho.');
    }
}
