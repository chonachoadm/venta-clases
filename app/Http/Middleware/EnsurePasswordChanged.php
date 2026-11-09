<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Routes a user flagged with must_change_password may still reach.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['password.change', 'password.change.update', 'logout'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs(self::ALLOWED_ROUTES)) {
            return redirect()->route('password.change')
                ->with('message', 'Tenés que elegir una contraseña antes de continuar.');
        }

        return $next($request);
    }
}
