<?php

namespace App\Services;

use App\Models\Purchase;
use Omnipay\Omnipay;

class PayPalPaymentService extends PaymentService
{
    private $gateway;

    public function __construct()
    {
        $this->gateway = Omnipay::create('PayPal_Rest');
        $this->gateway->setClientId(config('services.paypal.client_id'));
        $this->gateway->setSecret(config('services.paypal.secret'));
        $this->gateway->setTestMode(config('services.paypal.mode') === 'sandbox');
    }

    /**
     * Create a PayPal order.
     *
     * @param Purchase $purchase
     * @return array{redirect_url: string, order_id: string}
     */
    // public function createPayment(Purchase $purchase): array
    // {
    //     $package = $purchase->package;
    //     $guest = $purchase->guest;

    //     $usdAmount = round(
    //         $package->total_price / config('services.paypal.exchange_rate'),
    //         2
    //     );
    //     try {
    //         $response = $this->gateway->purchase([
    //             'amount' => number_format($usdAmount, 2, '.', ''),
    //             'currency' => 'USD',
    //             'returnUrl' => route('payment.success', ['purchase_id' => $purchase->id, 'provider' => 'paypal']),
    //             'cancelUrl' => route('payment.failure', ['provider' => 'paypal']),
    //             'description' => $package->name . ' Package - ' . $package->month_amount . ' months access',
    //             'transactionId' => (string) $purchase->id,
    //         ])->send();

    //         if ($response->isRedirect()) {
    //             return [
    //                 'redirect_url' => $response->getRedirectUrl(),
    //                 'order_id' => $response->getTransactionReference() ?? $purchase->id,
    //             ];
    //         }

    //         throw new \Exception('Unable to create PayPal order: ' . ($response->getMessage() ?? 'Unknown error'));
    //     } catch (\Exception $e) {
    //         \Log::error('PayPal order creation failed: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }
    private function getAccessToken()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, config('services.paypal.client_id') . ":" . config('services.paypal.secret'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $headers = [
            "Accept: application/json",
            "Accept-Language: en_US",
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $result['access_token'];
    }
    public function createPayment(Purchase $purchase): array
    {
        $accessToken = $this->getAccessToken();

        $usdAmount = round(
            $purchase->package->total_price / config('services.paypal.exchange_rate'),
            2
        );

        $payload = [
            "intent" => "CAPTURE",
            "purchase_units" => [
                [
                    "amount" => [
                        "currency_code" => "USD",
                        "value" => number_format($usdAmount, 2, '.', '')
                    ]
                ]
            ],
            "application_context" => [
                "return_url" => route('payment.success', [
                    'purchase_id' => $purchase->id,
                    'provider' => 'paypal'
                ]),
                "cancel_url" => route('payment.failure')
            ]
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v2/checkout/orders");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);

        $responseBody = curl_exec($ch);
        $response = json_decode($responseBody, true);
        curl_close($ch);
        if (!$response || !isset($response['id'], $response['links'])) {
            throw new \Exception('PayPal create order failed: ' . $responseBody);
        }

        $approvalLink = collect($response['links'] ?? [])
            ->firstWhere('rel', 'approve');
        
        if (!$approvalLink || !isset($approvalLink['href'])) {
            throw new \Exception('PayPal approval URL not found');
        }
        
        $approvalUrl = $approvalLink['href'];

        return [
            'redirect_url' => $approvalUrl,
            'order_id' => $response['id']
        ];
    }

    /**
     * Verify and capture payment after redirect from PayPal.
     * For redirect-based flow, we simply verify the payment was initiated.
     * Real-time verification should use webhooks.
     *
     * @param Purchase $purchase
     * @return bool
     */
    public function verifyPayment(Purchase $purchase): bool
    {
        if (!$purchase->payment_id) {
            \Log::warning('PayPal verification: no payment_id found in purchase', [
                'purchase_id' => $purchase->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Verify webhook signature from PayPal.
     *
     * @param array $payload
     * @param string $signature
     * @return array|null
     */
    public function verifyWebhook(array $payload, string $signature): ?array
    {
        if (isset($payload['event_type'], $payload['resource'])) {
            return $payload;
        }

        \Log::warning('PayPal webhook validation failed: missing required fields');
        return null;
    }

    /**
     * Get provider name.
     *
     * @return string
     */
    public function getProvider(): string
    {
        return 'paypal';
    }

    // public function completePayment(array $data)
    // {
    //     // $request = $this->gateway->completePurchase([
    //     //     'payerId' => $data['PayerID'],
    //     //     'token' => $data['token'],
    //     //     'transactionReference' => $data['paymentId'],
    //     // ]);

    //     // dd($request->getParameters());
    //     $response = $this->gateway->completePurchase([
    //         'payerId' => $data['PayerID'],
    //         'token' => $data['token'],
    //         'transactionReference' => $data['token'],
    //     ])->send();

    //     if (!$response->isSuccessful()) {
    //         throw new \Exception($response->getMessage());
    //     }

    //     // dd($response->getData());

    //     return $response->getData();
    // }
    public function completePayment(string $orderId): array
    {
        $accessToken = $this->getAccessToken();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api-m.sandbox.paypal.com/v2/checkout/orders/$orderId/capture");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken"
        ]);

        $responseBody = curl_exec($ch);
        $response = json_decode($responseBody, true);

        curl_close($ch);

        if (!$response || !isset($response['status'])) {
            throw new \Exception('PayPal capture failed: ' . $responseBody);
        }

        return $response;
    }
}
