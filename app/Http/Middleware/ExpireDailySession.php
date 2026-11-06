<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpireDailySession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $today = now()->toDateString();
        $authenticatedOn = $request->session()->get('auth_day');

        if ($authenticatedOn && $authenticatedOn !== $today) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Sesi login berakhir tengah malam. Silakan masuk kembali.');
        }

        if (! $authenticatedOn) {
            $request->session()->put('auth_day', $today);
        }

        return $next($request);
    }
}
