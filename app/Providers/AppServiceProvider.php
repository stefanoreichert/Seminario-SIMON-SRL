<?php

namespace App\Providers;

use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simon\Acceso\Application\AccessRepository;
use Simon\Acceso\Infrastructure\SqlAccess;
use Simon\Acceso\UI\Console\BootstrapAccess;
use Simon\Acceso\UI\RequirePermission;
use Simon\ClientesAdministracion\Application\AddressRecords;
use Simon\ClientesAdministracion\Application\CurrentAccountRecords;
use Simon\ClientesAdministracion\Application\CustomerDirectory;
use Simon\ClientesAdministracion\Application\CustomerRecords;
use Simon\ClientesAdministracion\Application\DeliveryAddresses;
use Simon\ClientesAdministracion\Infrastructure\SqlCurrentAccounts;
use Simon\ClientesAdministracion\Infrastructure\SqlCustomers;
use Simon\ClientesAdministracion\Infrastructure\SqlDeliveryAddresses;
use Simon\ClientesAdministracion\UI\CurrentAccountsReady;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\InventarioTrazabilidad\Application\DispatchAuthorization;
use Simon\InventarioTrazabilidad\Application\InventorySetup;
use Simon\InventarioTrazabilidad\Application\InventoryStore;
use Simon\InventarioTrazabilidad\Application\OrderFulfillment;
use Simon\InventarioTrazabilidad\Application\PairUnits;
use Simon\InventarioTrazabilidad\Application\ProductionPlanningQueries;
use Simon\InventarioTrazabilidad\Application\SupplyLots;
use Simon\InventarioTrazabilidad\Application\TraceabilityRecords;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\InventarioTrazabilidad\Infrastructure\SqlPairUnits;
use Simon\InventarioTrazabilidad\Infrastructure\SqlProductionPlanning;
use Simon\InventarioTrazabilidad\Infrastructure\SqlSupplyLots;
use Simon\InventarioTrazabilidad\Infrastructure\SqlTraceability;
use Simon\InventarioTrazabilidad\UI\Console\VerifyInventoryAudit;
use Simon\InventarioTrazabilidad\UI\PickingReady;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\Shared\UI\Console\CreateDemoData;
use Simon\Shared\UI\PrivateOperations;
use Simon\VentasPedidos\Application\DispatchableOrders;
use Simon\VentasPedidos\Application\DispatchedOrderReferences;
use Simon\VentasPedidos\Application\OrderQueries;
use Simon\VentasPedidos\Application\OrderRegistration;
use Simon\VentasPedidos\Application\OrderWorkflow;
use Simon\VentasPedidos\Application\PendingDemand;
use Simon\VentasPedidos\Application\PendingOrderQueries;
use Simon\VentasPedidos\Application\QueryPendingOrders;
use Simon\VentasPedidos\Infrastructure\SqlOrderFulfillment;
use Simon\VentasPedidos\Infrastructure\SqlOrderRegistration;
use Simon\VentasPedidos\Infrastructure\SqlOrderWorkflow;
use Simon\VentasPedidos\Infrastructure\SqlPendingOrders;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $bindings = [
            AccessPolicy::class => SqlAccess::class,
            AccessRepository::class => SqlAccess::class,
            UnitOfWork::class => SqlInventory::class,
            InventoryStore::class => SqlInventory::class,
            PairUnits::class => SqlPairUnits::class,
            SupplyLots::class => SqlSupplyLots::class,
            InventorySetup::class => SqlInventory::class,
            CatalogVariants::class => SqlInventory::class,
            ProductionPlanningQueries::class => SqlProductionPlanning::class,
            OrderFulfillment::class => SqlOrderFulfillment::class,
            DispatchedOrderReferences::class => SqlOrderFulfillment::class,
            TraceabilityRecords::class => SqlTraceability::class,
            DispatchAuthorization::class => SqlCustomers::class,
            CustomerRecords::class => SqlCustomers::class,
            CurrentAccountRecords::class => SqlCurrentAccounts::class,
            CustomerDirectory::class => SqlCustomers::class,
            AddressRecords::class => SqlDeliveryAddresses::class,
            DeliveryAddresses::class => SqlDeliveryAddresses::class,
            OrderRegistration::class => SqlOrderRegistration::class,
            OrderQueries::class => SqlOrderRegistration::class,
            OrderWorkflow::class => SqlOrderWorkflow::class,
            DispatchableOrders::class => SqlOrderWorkflow::class,
            PendingOrderQueries::class => SqlPendingOrders::class,
            PendingDemand::class => QueryPendingOrders::class,
        ];

        foreach ($bindings as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([AuthenticateSession::class, PrivateOperations::class, RequirePermission::class, PickingReady::class, CurrentAccountsReady::class]);
        Blade::anonymousComponentPath(base_path('src/Shared/UI/Views/components'), 'simon');
        $this->loadMigrationsFrom(glob(base_path('src/*/Infrastructure/Database/Migrations')) ?: []);
        $this->loadViewsFrom(base_path('src/Shared/UI/Views'), 'shared');
        $this->loadViewsFrom(base_path('src/Acceso/UI/Views'), 'acceso');
        $this->loadViewsFrom(base_path('src/ClientesAdministracion/UI/Views'), 'clientes');
        $this->loadViewsFrom(base_path('src/InventarioTrazabilidad/UI/Views'), 'inventario');
        $this->loadViewsFrom(base_path('src/VentasPedidos/UI/Views'), 'ventas');
        $this->commands([
            CreateDemoData::class,
            BootstrapAccess::class,
            VerifyInventoryAudit::class,
        ]);
    }
}
