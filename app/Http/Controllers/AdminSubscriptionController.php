<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Subscription;
use App\Services\PaymentServiceFactory;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filterMonths = in_array($request->filter, ['1', '3', '6', '12'], true) ? (int) $request->filter : null;

        $subscriptions = Subscription::with(['user', 'guest', 'package'])
            ->when($filterMonths, fn ($query) => $query->whereHas('package', fn ($package) => $package->where('month_amount', $filterMonths)))
            ->get();

        $purchases = Purchase::with(['user', 'guest', 'package'])
            ->when($filterMonths, fn ($query) => $query->whereHas('package', fn ($package) => $package->where('month_amount', $filterMonths)))
            ->get();

        $subscriptions = $subscriptions->concat($purchases)->sortByDesc('created_at')->values();

        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $paymentService = PaymentServiceFactory::make($subscription->provider);

        try {
            $paymentService->cancelSubscriptionService($subscription);

            // An admin cancellation ends the access right away, unlike a cancellation made by the customer.
            $subscription->update(['expires_at' => now(), 'cancelled_at' => now()]);

            return back()->with(
                'success',
                'La suscripción fue cancelada y el acceso terminó.'
            );

        } catch (\Throwable $e) {

            \Log::error('Error al cancelar suscripción', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'subscription' => 'No se pudo cancelar la suscripción. Intentá nuevamente.',
            ]);
        }
    }

    public function cancelPurchase(Purchase $purchase)
    {
        $purchase->update(['status' => 'cancelled']);

        return back()->with('success', 'La compra fue cancelada y el acceso terminó.');
    }
}
