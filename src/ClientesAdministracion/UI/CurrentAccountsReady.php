<?php

namespace Simon\ClientesAdministracion\UI;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class CurrentAccountsReady
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Schema::hasTable('customer_current_account_entries'), 503, 'La cuenta corriente está pendiente de activación por administración.');

        return $next($request);
    }
}
