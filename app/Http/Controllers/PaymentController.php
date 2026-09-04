<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\AccessGrantService;
use App\Services\PayPalPaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private AccessGrantService $accessGrantService,
        private PayPalPaymentService $payPalPaymentService,
    ) {}

    /**
     * Handle the buyer's return from a payment provider.
     */
    public function success(Request $request)
    {
        // PayPal one-time (Orders v2) requires an explicit capture call once the buyer approves.
        if ($request->filled('token') && $request->filled('PayerID')) {
            return $this->capturePayPalOrder($request->string('token'));
        }
        

        // PayPal subscription approval and MercadoPago (either flow) are activated via webhook.
        return redirect()->route('index')
            ->with('success', '¡Listo! En breve te llegará un correo para terminar de crear tu usuario.');
    }

    public function failure(Request $request)
    {
        return redirect()->route('index')
            ->with('error', 'El pago no pudo completarse. Intentá de nuevo más tarde.');
    }

    public function pending(Request $request)
    {
        return redirect()->route('index')
            ->with('message', 'Tu pago está pendiente de confirmación.');
    }

    private function capturePayPalOrder(string $orderId)
    {
        $purchase = Purchase::where('external_id', $orderId)->first();

        if (! $purchase) {
            return redirect()->route('payment.failure');
        }

        if ($purchase->status !== 'active') {
            try {
                $response = $this->payPalPaymentService->completePayment($orderId);
            } catch (\Throwable $e) {
                \Log::error('PayPal capture error', ['message' => $e->getMessage()]);

                return redirect()->route('payment.failure');
            }

            if (($response['status'] ?? null) !== 'COMPLETED') {
                return redirect()->route('payment.failure');
            }

            $this->accessGrantService->activatePurchase($purchase, $orderId);
        }

        return redirect()->route('index')
            ->with('success', '¡Listo! En breve te llegará un correo para terminar de crear tu usuario.');
    }
}
