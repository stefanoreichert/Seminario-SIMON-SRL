<?php

namespace Simon\Shared\UI;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PrivateOperations
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) ($request->user()?->fresh()?->is_active ?? false), 403);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
