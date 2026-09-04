<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use MercadoPago\MercadoPagoConfig;

// use MercadoPago\Client\Preference\PreferenceClient;

class MercadoPagoPaymentService extends PaymentService
{
    public function __construct()
    {
        $token = config('services.mercadopago.access_token');
        if (!$token) {
            throw new \Exception('Mercado Pago access token not configured. Please set MERCADOPAGO_ACCESS_TOKEN in .env');
        }
        MercadoPagoConfig::setAccessToken($token);
    }

    public function createSubscription(Subscription $subscription, Guest $guest, Package $package): array
    {

        $payerEmail = $guest->email;

        \Log::info('EMAIL CAPTURED', ['email' => $payerEmail]);
        try {
            $response = Http::withToken(config('services.mercadopago.access_token'))
                ->post('https://api.mercadopago.com/preapproval', [
                    'back_url' => config('app.url'),
                    'reason' => $package->name,
                    'external_reference' => (string) $subscription->id,
                    'payer_email' => $payerEmail,
                    'auto_recurring' => [
                        'frequency' => $package->month_amount,
                        'frequency_type' => 'months',
                        'transaction_amount' => $package->totalPriceArs(),
                        'currency_id' => 'ARS',
                    ],
                    'status' => 'pending',
                ]);

            $data = $response->json();
            \Log::info('MP FULL RESPONSE', $data);

            if (!$response->successful()) {
                throw new \Exception($data['message'] ?? 'Error al crear la suscripción.');
            }

            $subscription->update([
                'external_id' => $data['id'],
            ]);

            return [
                'redirect_url' => $data['init_point'],
            ];
        } catch (\Throwable $e) {

            \Log::error('Mercado Pago Subscription Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'redirect_url' => env('APP_URL'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createOneTimePayment(Purchase $purchase, Guest $guest, Package $package): array
    {
        try {
            $response = Http::withToken(config('services.mercadopago.access_token'))
                ->post('https://api.mercadopago.com/checkout/preferences', [
                    'items' => [
                        [
                            'title' => $package->name,
                            'quantity' => 1,
                            'unit_price' => $package->totalPriceArs(),
                            'currency_id' => 'ARS',
                        ],
                    ],
                    'external_reference' => (string) $purchase->id,
                    'back_urls' => [
                        'success' => route('payment.success'),
                        'failure' => route('payment.failure'),
                        'pending' => route('payment.pending'),
                    ],
                    'notification_url' => route('webhooks.mercadopago'),
                    'auto_return' => 'approved'
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                throw new \Exception($data['message'] ?? 'Error al crear el pago.');
            }

            $purchase->update([
                'external_id' => $data['id'],
            ]);

            // \Log::info('Init Point');
            return [
                'redirect_url' => $data['init_point'],
            ];
        } catch (\Throwable $e) {

            \Log::error('Mercado Pago One-Time Payment Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'redirect_url' => env('APP_URL'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelSubscriptionService(Subscription $subscription): bool
    {
        if (!$subscription->external_id) {
            throw new \Exception('La suscripción no tiene un ID de Mercado Pago.');
        }

        $response = Http::withToken(config('services.mercadopago.access_token'))
            ->put(
                "https://api.mercadopago.com/preapproval/{$subscription->external_id}",
                [
                    'status' => 'cancelled',
                ]
            );

        if (!$response->successful()) {
            \Log::error('Error cancelando suscripción de Mercado Pago', [
                'subscription_id' => $subscription->id,
                'external_id' => $subscription->external_id,
                'response' => $response->json(),
            ]);

            throw new \Exception(
                $response->json('message') ?? 'No se pudo cancelar la suscripción en Mercado Pago.'
            );
        }

        $subscription->update([
            'status' => 'cancelled',
        ]);

        return true;
    }

    /**
     * Get provider name.
     */
    public function getProvider(): string
    {
        return 'mercadopago';
    }
}
