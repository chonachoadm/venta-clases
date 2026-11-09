<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureActivePurchase;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'activePurchase' => EnsureActivePurchase::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/mercadopago',
            'payment/paypal-webhook',
        ]);
        $middleware->appendToGroup('web', EnsurePasswordChanged::class);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
