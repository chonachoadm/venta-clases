<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Elegí tu contraseña')
                ->greeting('¡Hola!')
                ->line('Recibimos un pedido para elegir la contraseña de tu cuenta.')
                ->action('Elegir mi contraseña', $url)
                ->line('El enlace vence en 24 horas y solo se puede usar una vez.')
                ->line('Si no lo pediste, ignorá este mensaje.')
                ->salutation('Gi Braier Clases');
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // The MercadoPago SDK relies on this static config being set for every request, not just when MercadoPagoPaymentService is instantiated.
        if ($token = config('services.mercadopago.access_token')) {
            MercadoPagoConfig::setAccessToken($token);
        }
    }
}
