<?php

namespace Simon\InventarioTrazabilidad\UI;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class PickingReady
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Schema::hasTable('inventory_pair_units') && Schema::hasTable('inventory_supply_lots'), 503, 'El módulo de identificación e insumos está pendiente de activación por administración.');

        return $next($request);
    }
}
