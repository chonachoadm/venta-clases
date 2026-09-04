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
        $filterPackageId = in_array($request->filter, ['1', '3', '6', '12'], true) ? (int) $request->filter : null;

        $subscriptions = Subscription::with(['user', 'guest', 'package'])
            ->when($filterPackageId, fn ($query) => $query->where('package_id', $filterPackageId))
            ->get();

        $purchases = Purchase::with(['user', 'guest', 'package'])
            ->when($filterPackageId, fn ($query) => $query->where('package_id', $filterPackageId))
            ->get();

        $subscriptions = $subscriptions->concat($purchases)->sortByDesc('created_at')->values();

        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $paymentService = PaymentServiceFactory::make($subscription->provider);

        try {
            $paymentService->cancelSubscriptionService($subscription);

            return back()->with(
                'success',
                'Tu suscripción fue cancelada correctamente.'
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

        return back()->with('success', 'La compra fue cancelada correctamente.');
    }
}
