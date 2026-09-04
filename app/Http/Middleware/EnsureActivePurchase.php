<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePurchase
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('index')
                ->with('message', 'Tenés que iniciar sesión.');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if ($user->activeSubscription()->doesntExist() && $user->activePurchase()->doesntExist()) {
            return redirect()->route('index')
                ->with('error', 'No tenés una compra activa en este momento.');
        }

        return $next($request);
    }
}
