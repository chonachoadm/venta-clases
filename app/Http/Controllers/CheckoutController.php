<?php

namespace App\Http\Controllers;

use App\Services\PaymentServiceFactory;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Guest;
use App\Models\Subscription;

class CheckoutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $packages = Package::all();
        return view('checkout', compact('packages'));
    }

    public function showCheckout(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $old = Subscription::whereHas('guest', function ($q) use ($validated) {
            $q->where('email', $validated['email']);
        })->where('status', 'active')->first();

        if ($old) {
            return back()->withErrors([
                'email' => 'Este email ya tiene una suscripción activa.'
            ]);
        }

        $guest = Guest::firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
            ]
        );

        $package = Package::findOrFail($validated['package_id']);

        $subscription = Subscription::create([
            'guest_id' => $guest->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);

        $paymentService = PaymentServiceFactory::make('mercadopago');

        $result = $paymentService->createSubscription($subscription, $guest, $package);

        return redirect()->away($result['redirect_url'])->with('success', '¡Bienvenidx! En breve te llegará un correo para terminar de crear tu usuario.');

    }
}
