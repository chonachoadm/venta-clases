<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use MercadoPago\MercadoPagoConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // The MercadoPago SDK relies on this static config being set for every request, not just when MercadoPagoPaymentService is instantiated.
        if ($token = config('services.mercadopago.access_token')) {
            MercadoPagoConfig::setAccessToken($token);
        }
    }
}
