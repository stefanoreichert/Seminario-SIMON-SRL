<?php

use Illuminate\Support\Facades\Route;
use Simon\Acceso\UI\AccessController;
use Simon\Acceso\UI\RequirePermission;
use Simon\ClientesAdministracion\UI\CustomerController;
use Simon\InventarioTrazabilidad\UI\OperationsController;
use Simon\Shared\UI\PrivateOperations;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', PrivateOperations::class])->prefix('gestion')->group(function (): void {
    Route::get('usuarios', [AccessController::class, 'index'])->name('access.index')->middleware(RequirePermission::class.':access.manage');
    Route::post('usuarios/roles', [AccessController::class, 'role'])->name('access.role')->middleware(RequirePermission::class.':access.manage');
    Route::post('usuarios', [AccessController::class, 'user'])->name('access.user')->middleware(RequirePermission::class.':access.manage');
    Route::middleware(RequirePermission::class.':clients.manage')->group(function (): void {
        Route::get('clientes', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('clientes/nuevo', [CustomerController::class, 'create'])->name('customers.create');
        Route::get('clientes/{customerId}', [CustomerController::class, 'show'])->whereNumber('customerId')->name('customers.show');
        Route::post('clientes', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('clientes/{customerId}', [CustomerController::class, 'update'])->whereNumber('customerId')->name('customers.update');
        Route::post('clientes/{customerId}/historial', [CustomerController::class, 'credit'])->whereNumber('customerId')->name('customers.credit');
    });
    Route::get('operaciones', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('operaciones/saldos', [OperationsController::class, 'index'])->name('operations.snapshot');

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
