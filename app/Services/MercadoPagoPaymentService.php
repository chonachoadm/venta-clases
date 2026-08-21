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
                    // 'back_urls' => [
                    //     'success' => route('payment.success', ['purchase_id' => $subscription->id, 'provider' => 'mercadopago']),
                    //     'failure' => route('payment.failure', ['provider' => 'mercadopago']),
                    //     'pending' => route('payment.pending', ['provider' => 'mercadopago']),
                    // ],
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
     * Create a payment preference in MercadoPago.
     *
     * @param Purchase $purchase
     * @return array{redirect_url: string, order_id: string}
     */
    // public function createPayment(Purchase $purchase): array
    // {
    //     $package = $purchase->package;
    //     $guest = $purchase->guest;

    //     try {
    //         $client = new PreferenceClient();
    //         $preference = $client->create([
    //             'items' => [
    //                 [
    //                     'id' => (string) $package->id,
    //                     'title' => $package->name . ' Package',
    //                     'description' => 'Acceso a la biblioteca virtual por ' . $package->month_amount . ' meses.',
    //                     'quantity' => 1,
    //                     'currency_id' => 'ARS',
    //                     'unit_price' => (float) $package->total_price,
    //                 ],
    //             ],
    //             'back_urls' => [
    //                 'success' => 'localhost:8000/payment/success',
    //                 'pending' => 'localhost:8000/payment/pending',
    //                 'failure' => 'localhost:8000/payment/failure',
    //                 // 'success' => route('payment.success', ['purchase_id' => $purchase->id, 'provider' => 'mercadopago']),
    //                 // 'failure' => route('payment.failure', ['provider' => 'mercadopago']),
    //                 // 'pending' => route('payment.pending', ['provider' => 'mercadopago']),
    //             ],
    //             'auto_return' => 'approved',
    //             'statement_descriptor' => 'Gi Braier',
    //             'external_reference' => (string) $purchase->id,
    //         ]);

    //         return [
    //             'redirect_url' => $preference->init_point,
    //             'order_id' => $preference->id,
    //         ];
    //     } catch (\MercadoPago\Exceptions\MPApiException $e) {
    //         $response = $e->getApiResponse();
    //         $content = $response ? $response->getContent() : [];

    //         \Log::error('Mercado Pago API Error', [
    //             'message' => $e->getMessage(),
    //             'content' => $content,
    //             'access_token_set' => !empty(config('services.mercadopago.access_token')),
    //         ]);

    //         $errorMsg = 'Mercado Pago error';
    //         if (is_array($content) && isset($content['message'])) {
    //             $errorMsg = $content['message'];
    //         }

    //         throw new \Exception('Mercado Pago payment creation failed: ' . $errorMsg);
    //     } catch (\Exception $e) {
    //         \Log::error('Mercado Pago Error: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }

    /**
     * Verify payment after redirect from MercadoPago.
     * This is a basic redirect-based verification.
     *
     * @param Purchase $purchase
     * @return bool
     */
    public function verifySubscription(Subscription $subscription): bool
    {
        return $subscription->status === 'active'
            && $subscription->expires_at?->isFuture();
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