<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->user() || auth()->user()->role !== 1) {
            return redirect()->route('index')
                ->with('error', 'No tenés permiso para acceder a esta sección.');
        }
        return $next($request);
    }

    
}
