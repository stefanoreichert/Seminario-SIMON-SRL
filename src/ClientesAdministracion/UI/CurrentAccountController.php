<?php

namespace Simon\ClientesAdministracion\UI;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Simon\ClientesAdministracion\Application\ManageCurrentAccounts;
use Simon\ClientesAdministracion\Domain\AccountEntryKind;
use Simon\ClientesAdministracion\Domain\Money;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\HandlesOperations;

final class CurrentAccountController extends Controller
{
    use HandlesOperations;

    public function index(Request $request, ManageCurrentAccounts $accounts, AccessPolicy $access): View
    {
        $data = $request->validate(['customer_id' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:255'], 'customer_page' => ['nullable', 'integer', 'min:1'], 'entry_page' => ['nullable', 'integer', 'min:1']]);
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        $report = $accounts->overview($request->user()->id, $customerId, $data['q'] ?? '', (int) ($data['customer_page'] ?? 1), (int) ($data['entry_page'] ?? 1));
        abort_if($customerId !== null && $report['customer'] === null, 404);

        return view('clientes::current-account', ['report' => $report, 'query' => $data['q'] ?? '', 'canManage' => $access->allows($request->user()->id, 'finance.manage'), 'operationId' => (string) Str::uuid(),
            'balance' => $report['account'] === null || ! $report['account']['initialized'] ? null : (new Money($report['account']['balance_minor'], $report['account']['currency'], $report['account']['minor_scale']))->formatted(),
        ]);
    }

    public function store(Request $request, ManageCurrentAccounts $accounts): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'customer_id' => ['required', 'integer', 'min:1'], 'kind' => ['required', Rule::enum(AccountEntryKind::class)], 'amount' => ['required', 'string', 'max:40'], 'source' => ['required', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:2000']]);

        return $this->perform(fn (): int => $accounts->post($request->user()->id, $data['operation_id'], (int) $data['customer_id'], $data['kind'], $data['amount'], $data['source'], $data['note'] ?? ''), 'Movimiento financiero registrado con su fuente. El stock y los pedidos conservan sus cantidades.', route('current-accounts.index', ['customer_id' => $data['customer_id']]));
    }
}
