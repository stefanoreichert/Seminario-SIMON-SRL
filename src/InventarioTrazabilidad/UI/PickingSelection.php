<?php

namespace Simon\InventarioTrazabilidad\UI;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Simon\InventarioTrazabilidad\Application\ManagePicking;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;

final class PickingSelection extends Component
{
    #[Locked]
    #[Url(as: 'line_id', except: null)]
    public ?int $lineId = null;

    #[Locked]
    #[Url(as: 'batch_id', except: null)]
    public ?int $batchId = null;

    #[Locked]
    #[Url(except: 1)]
    public int $page = 1;

    #[Locked]
    public string $operationId;

    public string $code = '';

    public string $lineInput = '';

    public string $batchInput = '';

    public string $status = '';

    public function mount(?int $lineId, ?int $batchId, int $page): void
    {
        $this->lineId = $lineId;
        $this->batchId = $batchId;
        $this->page = $page;
        $this->lineInput = $lineId === null ? '' : (string) $lineId;
        $this->batchInput = $batchId === null ? '' : (string) $batchId;
        $this->operationId = (string) Str::uuid();
    }

    public function search(): void
    {
        $data = $this->validate(['lineInput' => ['nullable', 'integer', 'min:1'], 'batchInput' => ['nullable', 'integer', 'min:1']]);
        $this->lineId = $data['lineInput'] === '' || $data['lineInput'] === null ? null : (int) $data['lineInput'];
        $this->batchId = $data['batchInput'] === '' || $data['batchInput'] === null ? null : (int) $data['batchInput'];
        $this->page = 1;
        $this->code = '';
        $this->status = '';
        if ($this->lineId !== null) {
            $this->dispatch('picking-ready');
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function scan(ManagePicking $picking): void
    {
        $this->validate(['code' => ['required', 'string', 'max:100']]);
        $this->changeSelection($picking, $this->code, false);
    }

    public function release(string $code, ManagePicking $picking): void
    {
        validator(['code' => $code], ['code' => ['required', 'string', 'max:100']])->validate();
        $this->changeSelection($picking, $code, true);
    }

    private function changeSelection(ManagePicking $picking, string $code, bool $release): void
    {
        abort_if($this->lineId === null, 422);
        $this->status = '';

        try {
            $picking->scan((int) auth()->id(), $this->operationId, $this->lineId, $code, $release);
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        } catch (BusinessRuleViolation $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        $this->resetErrorBag();
        $this->code = '';
        $this->operationId = (string) Str::uuid();
        $this->status = $release ? 'Par retirado de la selección.' : 'Par leído y seleccionado. Podés leer el siguiente.';
    }

    public function render(ManagePicking $picking, AccessPolicy $access): View
    {
        abort_unless(auth()->check() && (bool) auth()->user()->fresh()?->is_active, 403);

        try {
            $report = $picking->overview((int) auth()->id(), $this->lineId, $this->batchId, $this->page);
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        }

        return view('inventario::picking-selection', [
            'report' => $report,
            'canDispatch' => $access->allows((int) auth()->id(), 'inventory.dispatch'),
        ]);
    }
}
