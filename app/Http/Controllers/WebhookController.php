<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Subscription;
use App\Services\AccessGrantService;
use App\Services\MercadoPagoWebhookSignature;
use App\Services\PayPalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use MercadoPago\Client\PreApproval\PreApprovalClient;
use MercadoPago\Exceptions\MPApiException;

class WebhookController extends Controller
{
    public function __construct(
        private AccessGrantService $accessGrantService,
        private PayPalPaymentService $payPalPaymentService,
        private MercadoPagoWebhookSignature $mercadoPagoWebhookSignature,
    ) {}

    /**
     * Handle Mercado Pago webhook notifications
     */
    public function handleMercadoPagoWebhook(Request $request)
    {
        if (! $this->mercadoPagoWebhookSignature->isValid($request)) {
            \Log::warning('Mercado Pago webhook signature verification failed');

            return response()->json(['error' => 'invalid signature'], 401);
        }

        // Only identifiers are logged, never the payload (it carries payer and payment data).
        \Log::info('MercadoPago webhook received', [
            'type' => $request->input('type', $request->input('topic')),
            'id' => $request->input('data.id', $request->input('id')),
        ]);

        $type = $request->input('type');
        $data = (array) $request->input('data', []);

        if ($type === 'subscription_preapproval') {
            return $this->handleSubscription($data);
        }

        if ($type === 'subscription_authorized_payment') {
            return $this->handleAuthorizedPayment($data);
        }

        if ($type === 'payment') {
            return $this->handlePayment($data);
        }

        // Legacy IPN-style notifications (topic/resource), used by /checkout/preferences.
        $topic = $request->input('topic');

        if ($topic === 'payment') {
            return $this->handlePayment(['id' => $request->input('id')]);
        }

        if ($topic === 'merchant_order') {
            return $this->handleMerchantOrder($request->input('resource'));
        }

        return response()->json(['ignored' => true]);
    }

    /**
     * Handle a legacy merchant_order notification by finding its approved payment, if any.
     */
    public function handleMerchantOrder(?string $resourceUrl)
    {
        // Only the numeric id is trusted; the host is never taken from the request, to keep the access token from leaving Mercado Pago.
        if (! $resourceUrl || ! preg_match('#/merchant_orders/(\d+)/?$#', (string) parse_url($resourceUrl, PHP_URL_PATH), $matches)) {
            return response()->json(['error' => 'invalid resource'], 400);
        }

        $response = Http::withToken(config('services.mercadopago.access_token'))
            ->get("https://api.mercadopago.com/merchant_orders/{$matches[1]}");

        if (! $response->successful()) {
            \Log::error('Error consultando merchant_order', [
                'resource' => $resourceUrl,
                'response' => $response->json(),
            ]);

            return response()->json(['ok' => true]);
        }

        $order = $response->json();
        $purchase = Purchase::find($order['external_reference'] ?? null);

        if (! $purchase) {
            return response()->json(['error' => 'not found']);
        }

        $approvedPayment = collect($order['payments'] ?? [])->firstWhere('status', 'approved');

        if ($approvedPayment) {
            $this->accessGrantService->activatePurchase($purchase, (string) $approvedPayment['id']);
        }

        return response()->json(['ok' => true]);
    }

    public function handleSubscription(array $data)
    {
        $subscriptionId = $data['id'] ?? null;

        if (! $subscriptionId) {
            return response()->json(['error' => 'missing id']);
        }

        $client = new PreApprovalClient;
        try {
            $preapproval = $client->get($subscriptionId);
        } catch (MPApiException $e) {
            $response = $e->getApiResponse();
            $content = $response ? $response->getContent() : [];

            \Log::error('Error consultando preapproval', [
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage(),
                'content' => $content,
                'status' => $response?->getStatusCode(),
            ]);

            return response()->json(['ok' => true]);
        }

        $subscription = Subscription::where('id', $preapproval->external_reference)->first();

        if (! $subscription) {
            return response()->json(['error' => 'not found']);
        }

        if ($preapproval->status === 'authorized') {
            $this->accessGrantService->activateSubscription($subscription, $preapproval->id);
        }

        if (in_array($preapproval->status, ['cancelled', 'paused'], true)) {
            $this->markSubscriptionCancelled($subscription);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle a Mercado Pago one-time payment notification.
     */
    public function handlePayment(array $data)
    {
        $paymentId = $data['id'] ?? null;

        if (! $paymentId) {
            return response()->json(['error' => 'missing id']);
        }

        $response = Http::withToken(config('services.mercadopago.access_token'))
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (! $response->successful()) {
            \Log::error('Error consultando pago de Mercado Pago', [
                'payment_id' => $paymentId,
                'response' => $response->json(),
            ]);

            return response()->json(['ok' => true]);
        }

        $payment = $response->json();

        if (($payment['status'] ?? null) !== 'approved') {
            return response()->json(['ok' => true]);
        }

        // Charges of a subscription reuse the subscription id as external_reference and are handled by handleAuthorizedPayment.
        if (($payment['operation_type'] ?? null) === 'recurring_payment') {
            return response()->json(['ok' => true]);
        }

        $purchase = Purchase::where('provider', 'mercadopago')->find($payment['external_reference'] ?? null);

        if (! $purchase) {
            \Log::error('Compra no encontrada en handlePayment');

            return response()->json(['error' => 'not found']);
        }

        $this->accessGrantService->activatePurchase($purchase, (string) $paymentId);

        return response()->json(['ok' => true]);
    }

    /**
     * Handle a Mercado Pago recurring charge of a subscription (renewal).
     *
     * An authorized payment can be "processed" even when its payment was declined, so only an approved payment counts.
     */
    public function handleAuthorizedPayment(array $data)
    {
        $authorizedPaymentId = $data['id'] ?? null;

        if (! $authorizedPaymentId || ! ctype_digit((string) $authorizedPaymentId)) {
            return response()->json(['error' => 'missing id']);
        }

        $response = Http::withToken(config('services.mercadopago.access_token'))
            ->timeout(15)
            ->get("https://api.mercadopago.com/authorized_payments/{$authorizedPaymentId}");

        if (! $response->successful()) {
            \Log::error('Error consultando pago autorizado de Mercado Pago', [
                'authorized_payment_id' => $authorizedPaymentId,
                'status' => $response->status(),
            ]);

            // A server error is worth a retry from Mercado Pago, otherwise a renewal could be lost.
            return response()->json(['ok' => false], $response->serverError() ? 502 : 200);
        }

        $authorizedPayment = $response->json();
        $payment = $authorizedPayment['payment'] ?? [];

        if (($payment['status'] ?? null) !== 'approved' || empty($payment['id']) || empty($authorizedPayment['preapproval_id'])) {
            return response()->json(['ok' => true]);
        }

        $subscription = Subscription::where('provider', 'mercadopago')
            ->where('external_id', $authorizedPayment['preapproval_id'])
            ->first();

        if (! $subscription) {
            \Log::error('Suscripción no encontrada para el pago autorizado', ['preapproval_id' => $authorizedPayment['preapproval_id']]);

            return response()->json(['error' => 'not found']);
        }

        $this->accessGrantService->registerSubscriptionPayment($subscription, (string) $payment['id']);

        return response()->json(['ok' => true]);
    }

    /**
     * Handle PayPal webhook notifications
     */
    public function handlePayPalWebhook(Request $request)
    {
        if (! $this->payPalPaymentService->verifyWebhookSignature($request)) {
            \Log::warning('PayPal webhook signature verification failed');

            return response()->json(['error' => 'invalid signature'], 400);
        }

        $resource = $request->input('resource', []);

        return match ($request->input('event_type')) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($resource),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($resource),
            'PAYMENT.SALE.COMPLETED' => $this->handleSaleCompleted($resource),
            'PAYMENT.CAPTURE.COMPLETED' => $this->handlePaymentCaptureCompleted($resource),
            default => response()->json(['ignored' => true]),
        };
    }

    /**
     * A completed charge of a PayPal subscription: the first one and every renewal.
     */
    private function handleSaleCompleted(array $resource)
    {
        $agreementId = $resource['billing_agreement_id'] ?? null;
        $saleId = $resource['id'] ?? null;

        if (! $agreementId || ! $saleId) {
            return response()->json(['ignored' => true]);
        }

        $subscription = Subscription::where('provider', 'paypal')->where('external_id', $agreementId)->first();

        if (! $subscription) {
            \Log::error('Suscripción de PayPal no encontrada para el pago', ['billing_agreement_id' => $agreementId]);

            return response()->json(['error' => 'not found']);
        }

        $this->accessGrantService->registerSubscriptionPayment($subscription, $saleId);

        return response()->json(['ok' => true]);
    }

    private function handleSubscriptionActivated(array $resource)
    {
        $subscription = Subscription::find($resource['custom_id'] ?? null);

        if (! $subscription || empty($resource['id'])) {
            return response()->json(['error' => 'not found']);
        }

        $this->accessGrantService->activateSubscription($subscription, $resource['id']);

        return response()->json(['ok' => true]);
    }

    private function handleSubscriptionCancelled(array $resource)
    {
        if (empty($resource['id'])) {
            return response()->json(['error' => 'missing id']);
        }

        Subscription::where('external_id', $resource['id'])->get()->each(
            fn (Subscription $subscription) => $this->markSubscriptionCancelled($subscription)
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Only live subscriptions are cancelled; expired or already cancelled ones keep their status.
     */
    private function markSubscriptionCancelled(Subscription $subscription): void
    {
        if (in_array($subscription->status, ['pending', 'active'], true)) {
            $subscription->update(['status' => 'cancelled']);
        }
    }

    private function handlePaymentCaptureCompleted(array $resource)
    {
        $purchase = Purchase::find($resource['custom_id'] ?? null);

        if (! $purchase || empty($resource['id'])) {
            return response()->json(['ok' => true]);
        }

        $this->accessGrantService->activatePurchase($purchase, $resource['id']);

        return response()->json(['ok' => true]);
    }
}
