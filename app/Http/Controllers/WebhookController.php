<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Subscription;
use App\Services\AccessGrantService;
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
    ) {
    }

    /**
     * Handle Mercado Pago webhook notifications
     */
    public function handleMercadoPagoWebhook(Request $request)
    {
        \Log::info('MercadoPago webhook received', $request->all());

        $type = $request->input('type');
        $data = $request->input('data');

        if ($type === 'subscription_preapproval') {
            return $this->handleSubscription($data);
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
        if (!$resourceUrl) {
            return response()->json(['error' => 'missing resource']);
        }

        $response = Http::withToken(config('services.mercadopago.access_token'))->get($resourceUrl);

        if (!$response->successful()) {
            \Log::error('Error consultando merchant_order', [
                'resource' => $resourceUrl,
                'response' => $response->json(),
            ]);

            return response()->json(['ok' => true]);
        }

        $order = $response->json();
        $purchase = Purchase::find($order['external_reference'] ?? null);

        if (!$purchase) {
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

        if (!$subscriptionId) {
            return response()->json(['error' => 'missing id']);
        }

        $client = new PreApprovalClient;
        try {
            $preapproval = $client->get($subscriptionId);

            \Log::info('Preapproval obtenido', [
                'id' => $preapproval->id,
                'status' => $preapproval->status,
                'external_reference' => $preapproval->external_reference,
            ]);
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

        if (!$subscription) {
            return response()->json(['error' => 'not found']);
        }

        if ($preapproval->status === 'authorized') {
            $this->accessGrantService->activateSubscription($subscription, $preapproval->id);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle a Mercado Pago one-time payment notification.
     */
    public function handlePayment(array $data)
    {
        $paymentId = $data['id'] ?? null;

        if (!$paymentId) {
            return response()->json(['error' => 'missing id']);
        }

        $response = Http::withToken(config('services.mercadopago.access_token'))
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (!$response->successful()) {
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

        $purchase = Purchase::find($payment['external_reference'] ?? null);

        if (!$purchase) {
            \Log::error('Compra no encontrada en handlePayment');
            return response()->json(['error' => 'not found']);
        }
        \Log::info('Llega hasta acá 2');
        $this->accessGrantService->activatePurchase($purchase, (string) $paymentId);

        return response()->json(['ok' => true]);
    }

    /**
     * Handle PayPal webhook notifications
     */
    public function handlePayPalWebhook(Request $request)
    {
        if (!$this->payPalPaymentService->verifyWebhookSignature($request)) {
            \Log::warning('PayPal webhook signature verification failed');

            return response()->json(['error' => 'invalid signature'], 400);
        }

        $resource = $request->input('resource', []);

        return match ($request->input('event_type')) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($resource),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($resource),
            'PAYMENT.CAPTURE.COMPLETED' => $this->handlePaymentCaptureCompleted($resource),
            default => response()->json(['ignored' => true]),
        };
    }

    private function handleSubscriptionActivated(array $resource)
    {
        $subscription = Subscription::find($resource['custom_id'] ?? null);

        if (!$subscription) {
            return response()->json(['error' => 'not found']);
        }

        $this->accessGrantService->activateSubscription($subscription, $resource['id']);

        return response()->json(['ok' => true]);
    }

    private function handleSubscriptionCancelled(array $resource)
    {
        Subscription::where('external_id', $resource['id'] ?? null)->update(['status' => 'cancelled']);

        return response()->json(['ok' => true]);
    }

    private function handlePaymentCaptureCompleted(array $resource)
    {
        $purchase = Purchase::find($resource['custom_id'] ?? null);

        if (!$purchase) {
            return response()->json(['ok' => true]);
        }

        $this->accessGrantService->activatePurchase($purchase, $resource['id']);

        return response()->json(['ok' => true]);
    }
}
