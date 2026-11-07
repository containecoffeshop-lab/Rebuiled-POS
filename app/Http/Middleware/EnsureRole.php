<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $module = ModuleAccess::forRoute((string) $request->route()?->getName());
        abort_unless($user && (in_array($user->role, $roles, true) || ($module !== null && ModuleAccess::allows($user, $module))), 403);

        return $next($request);
    }
}
