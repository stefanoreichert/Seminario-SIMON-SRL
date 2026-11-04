<?php

namespace Simon\Acceso\UI;

use Closure;
use Illuminate\Http\Request;
use Simon\Shared\Application\AccessPolicy;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequirePermission
{
    public function __construct(private AccessPolicy $access) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $userId = $request->user()?->id;

        foreach ($permissions as $permission) {
            if ($userId !== null && $this->access->allows($userId, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'No tenés permiso para acceder a esta función.');
    }
}
