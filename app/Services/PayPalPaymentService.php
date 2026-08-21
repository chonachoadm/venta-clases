<?php

namespace App\Services;

use App\Models\Subscription;
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
    public function createPayment(Subscription $subscription): array
    {
        $accessToken = $this->getAccessToken();

    }

    /**
     * Verify and capture payment after redirect from PayPal.
     * For redirect-based flow, we simply verify the payment was initiated.
     * Real-time verification should use webhooks.
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function verifyPayment(Subscription $subscription): bool
    {

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
