<?php

namespace Simon\Shared\UI;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('es');

        return $next($request);
    }
}
