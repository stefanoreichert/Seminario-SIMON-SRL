<?php

use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Simon\Acceso\UI\AccessController;
use Simon\Acceso\UI\RequirePermission;
use Simon\ClientesAdministracion\UI\CustomerAddressController;
use Simon\ClientesAdministracion\UI\CustomerController;
use Simon\InventarioTrazabilidad\UI\OperationsController;
use Simon\Shared\UI\PanelLocale;
use Simon\Shared\UI\PrivateOperations;

Route::get('/', function () {
    return redirect()->route('filament.admin.pages.dashboard');
});

Route::middleware(['panel:admin', 'auth', AuthenticateSession::class, PrivateOperations::class, PanelLocale::class])->prefix('admin')->group(function (): void {
    Route::get('usuarios', [AccessController::class, 'index'])->name('access.index')->middleware(RequirePermission::class.':access.manage');
    Route::get('roles', [AccessController::class, 'index'])->defaults('section', 'roles')->name('access.roles')->middleware(RequirePermission::class.':access.manage');
    Route::post('usuarios/roles', [AccessController::class, 'role'])->name('access.role')->middleware(RequirePermission::class.':access.manage');
    Route::post('usuarios', [AccessController::class, 'user'])->name('access.user')->middleware(RequirePermission::class.':access.manage');
    Route::middleware(RequirePermission::class.':clients.manage')->group(function (): void {
        Route::get('clientes', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('clientes/nuevo', [CustomerController::class, 'create'])->name('customers.create');
        Route::get('clientes/{customerId}', [CustomerController::class, 'show'])->whereNumber('customerId')->name('customers.show');
        Route::post('clientes', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('clientes/{customerId}', [CustomerController::class, 'update'])->whereNumber('customerId')->name('customers.update');
        Route::post('clientes/{customerId}/historial', [CustomerController::class, 'credit'])->whereNumber('customerId')->name('customers.credit');
        Route::get('clientes/{customerId}/direcciones/nueva', [CustomerAddressController::class, 'create'])->whereNumber('customerId')->name('customers.addresses.create');
        Route::get('clientes/{customerId}/direcciones/{addressId}/editar', [CustomerAddressController::class, 'edit'])->whereNumber(['customerId', 'addressId'])->name('customers.addresses.edit');
        Route::post('clientes/{customerId}/direcciones', [CustomerAddressController::class, 'store'])->whereNumber('customerId')->name('customers.addresses.store');
        Route::put('clientes/{customerId}/direcciones/{addressId}', [CustomerAddressController::class, 'update'])->whereNumber(['customerId', 'addressId'])->name('customers.addresses.update');
    });
    Route::get('clientes/{customerId}/direcciones/opciones', [CustomerAddressController::class, 'options'])->whereNumber('customerId')
        ->middleware(RequirePermission::class.':clients.manage,orders.create,orders.manage,inventory.dispatch')->name('customers.addresses.options');
    Route::get('operaciones', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('operaciones/saldos', [OperationsController::class, 'index'])->name('operations.snapshot');
    foreach (['pedidos' => ['orders', 'operations.orders'], 'catalogo' => ['catalog', 'operations.catalog'], 'produccion' => ['production', 'operations.production.index'], 'stock' => ['stock', 'operations.stock'], 'despachos' => ['dispatch', 'operations.dispatch.index'], 'autorizaciones' => ['authorization', 'operations.authorization'], 'auditoria' => ['audit', 'operations.audit']] as $path => [$section, $name]) {
        Route::get($path, [OperationsController::class, 'index'])->defaults('section', $section)->name($name);
    }

    foreach ([
        'customer' => 'clients.manage', 'credit' => 'clients.manage', 'variant' => 'catalog.manage',
        'order' => 'orders.create,orders.manage', 'authorizeDispatch' => 'dispatch.authorize',
        'production' => 'production.manage', 'receive' => 'inventory.receive',
        'opening' => 'inventory.opening', 'dispatch' => 'inventory.dispatch',
    ] as $action => $permissions) {
        $path = $action === 'authorizeDispatch' ? 'authorize' : $action;
        Route::post('operaciones/'.$path, [OperationsController::class, $action])
            ->name('operations.'.$path)->middleware(RequirePermission::class.':'.$permissions);
    }
});

Route::match(['GET', 'HEAD', 'POST', 'PUT'], 'gestion/{path?}', function (Request $request, string $path = ''): RedirectResponse {
    $query = $request->getQueryString();

    return redirect(url('admin/'.$path).($query === null ? '' : '?'.$query), in_array($request->method(), ['GET', 'HEAD'], true) ? 302 : 307);
})->where('path', '[A-Za-z0-9/_-]*')->middleware(['auth', PrivateOperations::class]);
