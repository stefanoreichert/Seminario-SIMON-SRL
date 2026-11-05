<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Simon\Acceso\Application\AccessRepository;
use Simon\Acceso\Infrastructure\SqlAccess;
use Simon\Acceso\UI\Console\BootstrapAccess;
use Simon\ClientesAdministracion\Application\AddressRecords;
use Simon\ClientesAdministracion\Application\CustomerRecords;
use Simon\ClientesAdministracion\Application\DeliveryAddresses;
use Simon\ClientesAdministracion\Infrastructure\SqlCustomers;
use Simon\ClientesAdministracion\Infrastructure\SqlDeliveryAddresses;
use Simon\InventarioTrazabilidad\Application\CatalogVariants;
use Simon\InventarioTrazabilidad\Application\DispatchAuthorization;
use Simon\InventarioTrazabilidad\Application\InventorySetup;
use Simon\InventarioTrazabilidad\Application\InventoryStore;
use Simon\InventarioTrazabilidad\Application\OrderFulfillment;
use Simon\InventarioTrazabilidad\Infrastructure\SqlInventory;
use Simon\InventarioTrazabilidad\UI\Console\VerifyInventoryAudit;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\Application\UnitOfWork;
use Simon\VentasPedidos\Application\OrderQueries;
use Simon\VentasPedidos\Application\OrderRegistration;
use Simon\VentasPedidos\Infrastructure\SqlOrderFulfillment;
use Simon\VentasPedidos\Infrastructure\SqlOrderRegistration;

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
            InventorySetup::class => SqlInventory::class,
            CatalogVariants::class => SqlInventory::class,
            OrderFulfillment::class => SqlOrderFulfillment::class,
            DispatchAuthorization::class => SqlCustomers::class,
            CustomerRecords::class => SqlCustomers::class,
            AddressRecords::class => SqlDeliveryAddresses::class,
            DeliveryAddresses::class => SqlDeliveryAddresses::class,
            OrderRegistration::class => SqlOrderRegistration::class,
            OrderQueries::class => SqlOrderRegistration::class,
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
        Blade::anonymousComponentPath(base_path('src/Shared/UI/Views/components'), 'simon');
        $this->loadMigrationsFrom(glob(base_path('src/*/Infrastructure/Database/Migrations')) ?: []);
        $this->loadViewsFrom(base_path('src/Shared/UI/Views'), 'shared');
        $this->loadViewsFrom(base_path('src/Acceso/UI/Views'), 'acceso');
        $this->loadViewsFrom(base_path('src/ClientesAdministracion/UI/Views'), 'clientes');
        $this->loadViewsFrom(base_path('src/InventarioTrazabilidad/UI/Views'), 'inventario');
        $this->loadViewsFrom(base_path('src/VentasPedidos/UI/Views'), 'ventas');
        $this->commands([
            BootstrapAccess::class,
            VerifyInventoryAudit::class,
        ]);
    }
}
