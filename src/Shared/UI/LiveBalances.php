<?php

namespace Simon\Shared\UI;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Simon\InventarioTrazabilidad\UI\OperationsController;
use Simon\VentasPedidos\UI\PendingOrdersController;

#[Isolate]
final class LiveBalances extends Component
{
    #[Locked]
    public string $kind;

    /** @var array<string,mixed> */
    #[Locked]
    public array $parameters = [];

    /** @param array<string,mixed> $parameters */
    public function mount(string $kind, array $parameters): void
    {
        abort_unless(in_array($kind, ['operations', 'pending'], true), 404);
        $this->kind = $kind;
        $this->parameters = $parameters;
    }

    public function render(): View
    {
        abort_unless(auth()->check() && (bool) auth()->user()->fresh()?->is_active, 403);
        $url = route($this->kind === 'operations' ? 'operations.snapshot' : 'pending.snapshot', $this->parameters);
        $input = Request::create($url);
        $input->setUserResolver(fn () => auth()->user());
        $input->setLaravelSession(app('session.store'));
        $input->setRouteResolver(fn () => Route::getRoutes()->match($input));
        $controller = $this->kind === 'operations' ? OperationsController::class : PendingOrdersController::class;
        $view = app()->call([app($controller), 'index'], ['request' => $input]);

        return view('shared::live-balances', ['partial' => $view->name(), 'data' => $view->getData(), 'url' => $url]);
    }
}
