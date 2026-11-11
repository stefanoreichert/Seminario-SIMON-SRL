<?php

namespace Simon\Shared\UI;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use ReflectionMethod;
use Simon\Acceso\UI\RequirePermission;
use Simon\ClientesAdministracion\Application\ManageAddresses;
use Simon\ClientesAdministracion\UI\CurrentAccountsReady;
use Simon\InventarioTrazabilidad\UI\PickingReady;
use Simon\Shared\Domain\PermissionDenied;

final class ManagementActions extends Component
{
    private const array ACTIONS = [
        'access.role', 'access.user', 'customers.store', 'customers.update', 'customers.credit',
        'customers.addresses.store', 'customers.addresses.update', 'current-accounts.store',
        'picking.identify', 'picking.scan', 'picking.dispatch', 'supplies.store', 'supplies.configure',
        'orders.update', 'orders.transition', 'operations.customer', 'operations.credit',
        'operations.variant', 'operations.order', 'operations.authorize', 'operations.production',
        'operations.receive', 'operations.opening', 'operations.dispatch',
    ];

    /** @var array<string,list<string>> */
    public array $messages = [];

    /** @return list<array<string,mixed>> */
    public function addresses(int $customerId, ManageAddresses $addresses): array
    {
        abort_unless(auth()->check() && (bool) auth()->user()->fresh()?->is_active, 403);
        abort_if($customerId < 1, 422);
        try {
            return $addresses->options((int) auth()->id(), $customerId);
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        }
    }

    /** @return array{ok:bool,message?:string} */
    public function submit(string $path, string $method, array $fields): array
    {
        abort_unless(auth()->check() && (bool) auth()->user()->fresh()?->is_active, 403);
        abort_unless(in_array($method, ['POST', 'PUT'], true) && str_starts_with($path, '/admin/') && ! str_contains($path, '?'), 422);
        abort_if(strlen(json_encode($fields, JSON_THROW_ON_ERROR)) > 2097152, 413);
        unset($fields['_method']);
        $fields = $this->normalize($fields);
        $request = Request::create($path, $method, $fields);
        $request->setUserResolver(fn () => auth()->user());
        $request->setLaravelSession(app('session.store'));
        $route = clone Route::getRoutes()->match($request);
        abort_unless(in_array($route->getName(), self::ACTIONS, true), 404);
        $route->setContainer(app());
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $middleware = array_values(array_filter(app('router')->gatherRouteMiddleware($route), fn (string $name): bool => in_array(explode(':', $name, 2)[0], [RequirePermission::class, PickingReady::class, CurrentAccountsReady::class], true)));
        $this->messages = [];

        try {
            $response = app(Pipeline::class)->send($request)->through($middleware)->then(function (Request $request) use ($route): RedirectResponse {
                $controller = $route->getController();
                $method = $route->getActionMethod();
                $arguments = $route->parameters();

                foreach ((new ReflectionMethod($controller, $method))->getParameters() as $parameter) {
                    $type = $parameter->getType()?->getName();
                    if ($type !== null && is_a($type, Request::class, true)) {
                        $input = $type::createFrom($request);
                        if ($input instanceof FormRequest) {
                            $input->setContainer(app())->setRedirector(app('redirect'));
                            $input->validateResolved();
                        }
                        $arguments[$parameter->getName()] = $input;
                    }
                }

                return app()->call([$controller, $method], $arguments);
            });
        } catch (ValidationException $exception) {
            $this->messages = $exception->errors();

            return ['ok' => false];
        }

        $message = (string) session('status', '');
        $this->redirect($response->getTargetUrl(), navigate: true);

        return ['ok' => true, 'message' => $message];
    }

    public function render(): View
    {
        return view('shared::management-actions');
    }

    /** @param array<string|int,mixed> $fields
     * @return array<string|int,mixed>
     */
    private function normalize(array $fields): array
    {
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $fields[$key] = $this->normalize($value);
            } elseif (is_string($value)) {
                $value = in_array($key, ['password', 'password_confirmation', 'current_password'], true) ? $value : Str::trim($value);
                $fields[$key] = $value === '' ? null : $value;
            }
        }

        return $fields;
    }
}
