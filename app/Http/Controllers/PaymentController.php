<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Models\PaymentIntent;
use App\Services\PaymentServiceFactory;

class PaymentController extends Controller
{

    public function start(Purchase $purchase, string $provider)
    {
        if (!in_array($provider, ['mercadopago', 'paypal'])) {
            abort(404);
        }
        try {
            $paymentIntent = PaymentIntent::create([
                'purchase_id' => $purchase->id,
                'provider' => $provider,
                'status' => 'pending',
            ]);

            $paymentService = PaymentServiceFactory::make($provider);
            $paymentData = $paymentService->createPayment($purchase);

            $paymentIntent->update([
                'external_id' => $paymentData['order_id'],
            ]);

            return redirect()->away($paymentData['redirect_url']);
        } catch (\Throwable $th) {
            $paymentIntent->update([
                'status' => 'failed',
            ]);

            throw $th;
        }
    }
    /**
     * Handle successful payment return from any provider
     */
    public function success(Request $request)
    {
        $paymentIntent = null;

        // MERCADOPAGO
        if ($request->has('preference_id')) {
            $paymentIntent = PaymentIntent::where('external_id', $request->get('preference_id'))->first();
        }

        // PAYPAL (nuevo flujo)
        if ($request->has('purchase_id')) {
            $paymentIntent = PaymentIntent::where('purchase_id', $request->get('purchase_id'))
                ->where('provider', 'paypal')
                ->first();
        }

        if (!$paymentIntent) {
            return redirect()->route('payment.failure');
        }

        $purchase = $paymentIntent->purchase;

        if (!$purchase) {
            return redirect()->route('payment.failure');
        }

        $paymentService = PaymentServiceFactory::make($paymentIntent->provider);

        $isValid = false;

        if ($paymentIntent->provider === 'paypal') {

            $response = $paymentService->completePayment(
                $request->get('token')
            );

            $isValid = ($response['status'] ?? null) === 'COMPLETED';
        }

        if ($paymentIntent->provider === 'mercadopago') {

            $isValid = true;
        }

        if (!$isValid) {
            return redirect()->route('payment.failure');
        }

        $paymentIntent->update([
            'status' => 'approved',
        ]);

        $purchase->update([
            'status' => 'approved',
        ]);

        return view('payment-success', [
            'email' => $purchase->guest->email,
            'name' => $purchase->guest->name,
            'phone' => $purchase->guest->phone,
            'purchase' => $purchase,
        ]);
    }

    /**
     * Handle failed payment return from any provider
     */
    public function failure(Request $request)
    {
        return view('payment-failure', [
            'paymentId' => $request->query('preference_id') ?? $request->query('token'),
        ]);
    }

    /**
     * Handle pending payment return from any provider
     */
    public function pending(Request $request)
    {
        return view('payment-pending', [
            'paymentId' => $request->query('preference_id') ?? $request->query('token'),
        ]);
    }
}
