<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Simon\Shared\Application\AccessPolicy;
use Simon\Shared\UI\Dashboard;
use Simon\Shared\UI\PanelLocale;
use Simon\Shared\UI\PrivateOperations;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->spa()
            ->brandName('SIMON SRL')
            ->viteTheme('src/Shared/UI/Assets/filament.css')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(['Comercial', 'Fábrica', 'Administración'])
            ->login()
            ->navigationItems([
                $this->module('Clientes', 'customers.index', 'Comercial', 'heroicon-o-users', ['clients.manage'], 10, 'customers.*'),
                $this->module('Pedidos', 'operations.orders', 'Comercial', 'heroicon-o-shopping-bag', ['orders.create', 'orders.manage', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize'], 20, ['operations.orders', 'orders.*']),
                $this->module('Pendientes', 'pending.index', 'Comercial', 'heroicon-o-clipboard-document-list', ['orders.create', 'orders.manage', 'inventory.view', 'inventory.dispatch', 'dispatch.authorize'], 30, 'pending.*'),
                $this->module('Catálogo', 'operations.catalog', 'Fábrica', 'heroicon-o-squares-2x2', ['catalog.manage', 'inventory.view'], 10),
                $this->module('Producción', 'operations.production.index', 'Fábrica', 'heroicon-o-cog-6-tooth', ['production.manage', 'inventory.view', 'inventory.receive'], 20),
                $this->module('Stock', 'operations.stock', 'Fábrica', 'heroicon-o-cube', ['inventory.view', 'inventory.receive', 'inventory.opening', 'inventory.dispatch'], 30),
                $this->module('Identificación y picking', 'picking.index', 'Fábrica', 'heroicon-o-qr-code', ['inventory.view', 'inventory.receive', 'inventory.dispatch'], 31, 'picking.*'),
                $this->module('Insumos y vencimientos', 'supplies.index', 'Fábrica', 'heroicon-o-calendar-days', ['inventory.view', 'production.manage'], 32, 'supplies.*'),
                $this->module('Trazabilidad', 'traceability.index', 'Fábrica', 'heroicon-o-magnifying-glass', ['inventory.view', 'inventory.dispatch', 'audit.view', 'orders.manage', 'dispatch.authorize'], 35),
                $this->module('Despachos', 'operations.dispatch.index', 'Fábrica', 'heroicon-o-truck', ['inventory.dispatch'], 40),
                $this->module('Autorizaciones', 'operations.authorization', 'Administración', 'heroicon-o-shield-check', ['dispatch.authorize'], 10),
                $this->module('Cuenta corriente', 'current-accounts.index', 'Administración', 'heroicon-o-banknotes', ['finance.view', 'finance.manage'], 15, 'current-accounts.*'),
                $this->module('Usuarios', 'access.index', 'Administración', 'heroicon-o-user-circle', ['access.manage'], 20, ['access.index', 'access.roles']),
                $this->module('Auditoría', 'operations.audit', 'Administración', 'heroicon-o-clipboard-document-list', ['audit.view'], 40),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                PanelLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                PrivateOperations::class,
            ], isPersistent: true);
    }

    /** @param list<string> $permissions */
    private function module(string $label, string $route, string $group, string $icon, array $permissions, int $sort, string|array|null $activePattern = null): NavigationItem
    {
        return NavigationItem::make($label)->url(fn (): string => route($route))->group($group)->icon($icon)->sort($sort)
            ->isActiveWhen(fn (): bool => request()->routeIs(...(array) ($activePattern ?? $route)))
            ->visible(function () use ($permissions): bool {
                if (! auth()->check()) {
                    return false;
                }

                foreach ($permissions as $permission) {
                    if (app(AccessPolicy::class)->allows(auth()->id(), $permission)) {
                        return true;
                    }
                }

                return false;
            });
    }
}
