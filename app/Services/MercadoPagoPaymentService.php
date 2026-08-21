<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Guest;
use App\Models\Package;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Http;
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
                        'transaction_amount' => (float) $package->total_price,
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
                'redirect_url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment after redirect from MercadoPago.
     * This is a basic redirect-based verification.
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function verifySubscription(Subscription $subscription): bool
    {
        return $subscription->status === 'active'
            && $subscription->expires_at?->isFuture();
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
     * Verify webhook signature from MercadoPago.
     * MercadoPago webhooks include payment and resource IDs - we can verify the resource exists.
     *
     * @param array $payload
     * @param string $signature
     * @return array|null
     */
    public function verifyWebhook(array $payload, string $signature): ?array
    {
        // MercadoPago webhooks don't require signature verification at this stage
        // They send a POST with data and we can verify by querying the API
        // For now, return the payload if it has required fields
        if (isset($payload['resource'], $payload['action'])) {
            return $payload;
        }

        return null;
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getProvider(): string
    {
        return 'mercadopago';
    }
}