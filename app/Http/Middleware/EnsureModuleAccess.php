<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $module = ModuleAccess::forRoute((string) $request->route()?->getName());
        if ($module !== null) {
            $user = $request->user();
            $route = (string) $request->route()?->getName();
            $allowed = ModuleAccess::allows($user, $module)
                || (str_starts_with($route, 'pos.shift.') && ModuleAccess::allows($user, 'cycle_count'))
                || ($route === 'pos.orders.receipt' && ModuleAccess::allows($user, 'kasir'));
            abort_unless($allowed, 403);
        } elseif ($request->user()->role !== 'manager') {
            $route = (string) $request->route()?->getName();
            abort_unless($route === 'pos.index' || str_starts_with($route, 'pos.notifications.'), 403);
        }

        return $next($request);
    }
}
