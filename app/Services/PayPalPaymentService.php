<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayPalPaymentService extends PaymentService
{
    private string $baseUrl;

    public function __construct()
    {
        // $this->gateway = Omnipay::create('PayPal_Rest');
        // $this->gateway->setClientId(config('services.paypal.client_id'));
        // $this->gateway->setSecret(config('services.paypal.secret'));
        // $this->gateway->setTestMode(config('services.paypal.mode') === 'sandbox');
        $this->baseUrl = config('services.paypal.base_url');
    }

    private function getAccessToken()
    {
        // $ch = curl_init();

        // curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v1/oauth2/token");
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($ch, CURLOPT_USERPWD, config('services.paypal.client_id') . ":" . config('services.paypal.secret'));
        // curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        // $headers = [
        //     "Accept: application/json",
        //     "Accept-Language: en_US",
        // ];

        // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // $result = json_decode(curl_exec($ch), true);
        // curl_close($ch);

        // return $result['access_token'];

        $response = Http::asForm()
            ->withBasicAuth(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )
            ->post($this->baseUrl.'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();

        return $response->json('access_token');
    }

    private function createProduct(Package $package): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => uniqid(),
            ])
            ->post($this->baseUrl.'/v1/catalogs/products', [
                'name' => $package->name,
                'description' => 'Suscripción a Gi Braier por '.$package->name,
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Create the PayPal product + billing plan for a package and cache the plan id.
     */
    public function provisionPlan(Package $package): string
    {
        $accessToken = $this->getAccessToken();
        $product = $this->createProduct($package);

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'PayPal-Request-Id' => uniqid(),
            ])
            ->post($this->baseUrl.'/v1/billing/plans', [
                'product_id' => $product['id'],
                'name' => $package->name,
                'description' => 'Suscripción a Gi Braier por '.$package->name,
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => 'MONTH',
                            'interval_count' => $package->month_amount,
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => $this->usdAmount($package),
                                'currency_code' => 'USD',
                            ],
                        ],
                    ],
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'payment_failure_threshold' => 3,
                ],
            ]);

        $response->throw();

        $planId = $response->json('id');
        $package->update(['paypal_plan_id' => $planId]);

        return $planId;
    }

    public function createSubscription(Subscription $subscription, Guest $guest, Package $package): array
    {
        if (! $package->paypal_plan_id) {
            throw new \Exception('Este paquete no tiene un plan de PayPal provisionado. Ejecutá `php artisan paypal:provision-plans`.');
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders(['PayPal-Request-Id' => uniqid()])
                ->post($this->baseUrl.'/v1/billing/subscriptions', [
                    'plan_id' => $package->paypal_plan_id,
                    'custom_id' => (string) $subscription->id,
                    'subscriber' => [
                        'email_address' => $guest->email,
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'user_action' => 'SUBSCRIBE_NOW',
                        'return_url' => route('payment.success'),
                        'cancel_url' => route('payment.failure'),
                    ],
                ]);

            $data = $response->json();

            if (! $response->successful()) {
                throw new \Exception($data['message'] ?? 'Error al crear la suscripción de PayPal.');
            }

            $subscription->update(['external_id' => $data['id']]);

            return ['redirect_url' => $this->approveLink($data)];
        } catch (\Throwable $e) {
            \Log::error('PayPal Subscription Error', ['message' => $e->getMessage()]);

            return [
                'redirect_url' => route('index'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createOneTimePayment(Purchase $purchase, Guest $guest, Package $package): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders(['PayPal-Request-Id' => uniqid()])
                ->post($this->baseUrl.'/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'custom_id' => (string) $purchase->id,
                            'description' => $package->name,
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => $this->usdAmount($package),
                            ],
                        ],
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'user_action' => 'PAY_NOW',
                        'return_url' => route('payment.success'),
                        'cancel_url' => route('payment.failure'),
                    ],
                ]);

            $data = $response->json();

            if (! $response->successful()) {
                throw new \Exception($data['message'] ?? 'Error al crear el pago de PayPal.');
            }

            $purchase->update(['external_id' => $data['id']]);

            return ['redirect_url' => $this->approveLink($data)];
        } catch (\Throwable $e) {
            \Log::error('PayPal One-Time Payment Error', ['message' => $e->getMessage()]);

            return [
                'redirect_url' => route('index'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel an active PayPal subscription.
     */
    public function cancelSubscriptionService(Subscription $subscription): bool
    {
        if (! $subscription->external_id) {
            throw new \Exception('La suscripción no tiene un ID de PayPal.');
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl."/v1/billing/subscriptions/{$subscription->external_id}/cancel", [
                'reason' => 'Cancelada por el administrador',
            ]);

        if (! $response->successful()) {
            \Log::error('Error cancelando suscripción de PayPal', [
                'subscription_id' => $subscription->id,
                'external_id' => $subscription->external_id,
                'response' => $response->body(),
            ]);

            throw new \Exception('No se pudo cancelar la suscripción en PayPal.');
        }

        $subscription->update(['status' => 'cancelled']);

        return true;
    }

    /**
     * Format a package's USD total price the way PayPal expects it.
     */
    private function usdAmount(Package $package): string
    {
        return number_format($package->total_price, 2, '.', '');
    }

    /**
     * Pull the buyer-approval redirect link out of a PayPal order/subscription response.
     */
    private function approveLink(array $data): string
    {
        $link = collect($data['links'] ?? [])->firstWhere('rel', 'approve');

        if (! $link) {
            throw new \Exception('PayPal no devolvió un link de aprobación.');
        }

        return $link['href'];
    }

    /**
     * Verify an inbound PayPal webhook request's signature via PayPal's verification API.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (! $webhookId) {
            \Log::warning('PayPal webhook signature check skipped: PAYPAL_WEBHOOK_ID not configured');

            return false;
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl.'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('paypal-auth-algo'),
                'cert_url' => $request->header('paypal-cert-url'),
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * Get provider name.
     */
    public function getProvider(): string
    {
        return 'paypal';
    }

    public function completePayment(string $orderId): array
    {
        $accessToken = $this->getAccessToken();

        // An empty PHP array serializes to a JSON array ([]) rather than the empty object ({}) PayPal expects here.
        $response = Http::withToken($accessToken)
            ->withBody('{}', 'application/json')
            ->post($this->baseUrl."/v2/checkout/orders/{$orderId}/capture");

        $data = $response->json();

        if (! $data || ! isset($data['status'])) {
            throw new \Exception('PayPal capture failed: '.$response->body());
        }

        return $data;
    }
}
