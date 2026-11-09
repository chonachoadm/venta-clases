<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Services\PaymentServiceFactory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $hasActiveAccess = Subscription::whereHas('user', fn ($q) => $q->where('email', $validated['email']))
            ->where('status', 'active')
            ->exists()
            || Purchase::whereHas('user', fn ($q) => $q->where('email', $validated['email']))
                ->where('status', 'active')
                ->exists();

        if ($hasActiveAccess) {
            return back()->withErrors([
                'email' => 'Este email ya tiene una suscripción activa.',
            ]);
        }

        $guest = Guest::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
            ]
        );

        $package = Package::findOrFail($validated['package_id']);

        // Kept in session (not the request body) so pay* actions can't be pointed at another guest/package.
        session([
            'checkout.guest_id' => $guest->id,
            'checkout.package_id' => $package->id,
        ]);

        return view('checkout-conf', compact('guest', 'package'));
    }

    public function payWithMercadoPagoSubscription(): RedirectResponse
    {
        [$guest, $package] = $this->checkoutSelection();

        $subscription = Subscription::create([
            'guest_id' => $guest->id,
            'package_id' => $package->id,
            'provider' => 'mercadopago',
            'status' => 'pending',
        ]);

        $result = PaymentServiceFactory::make('mercadopago')->createSubscription($subscription, $guest, $package);

        return $this->redirectToProvider($result);
    }

    public function payWithMercadoPagoOneTime(): RedirectResponse
    {
        [$guest, $package] = $this->checkoutSelection();

        $purchase = Purchase::create([
            'guest_id' => $guest->id,
            'package_id' => $package->id,
            'provider' => 'mercadopago',
            'status' => 'pending',
        ]);

        $result = PaymentServiceFactory::make('mercadopago')->createOneTimePayment($purchase, $guest, $package);

        return $this->redirectToProvider($result);
    }

    public function payWithPayPalSubscription(): RedirectResponse
    {
        [$guest, $package] = $this->checkoutSelection();

        $subscription = Subscription::create([
            'guest_id' => $guest->id,
            'package_id' => $package->id,
            'provider' => 'paypal',
            'status' => 'pending',
        ]);

        $result = PaymentServiceFactory::make('paypal')->createSubscription($subscription, $guest, $package);

        return $this->redirectToProvider($result);
    }

    public function payWithPayPalOneTime(): RedirectResponse
    {
        [$guest, $package] = $this->checkoutSelection();

        $purchase = Purchase::create([
            'guest_id' => $guest->id,
            'package_id' => $package->id,
            'provider' => 'paypal',
            'status' => 'pending',
        ]);

        $result = PaymentServiceFactory::make('paypal')->createOneTimePayment($purchase, $guest, $package);

        return $this->redirectToProvider($result);
    }

    /**
     * @return array{0: Guest, 1: Package}
     */
    private function checkoutSelection(): array
    {
        $guest = Guest::find(session('checkout.guest_id'));
        $package = Package::find(session('checkout.package_id'));

        if (! $guest || ! $package) {
            throw new HttpResponseException(
                redirect()->route('checkout')->with('error', 'Tu sesión expiró. Elegí el paquete y completá tus datos de nuevo.')
            );
        }

        return [$guest, $package];
    }

    private function redirectToProvider(array $result): RedirectResponse
    {
        $url = $result['redirect_url'] ?? route('index');

        if (isset($result['error'])) {
            return redirect()->away($url)
                ->with('error', 'Hubo un error, intentá de nuevo más tarde.');
        }

        return redirect()->away($url)
            ->with('success', '¡Bienvenidx! En breve te llegará un correo para terminar de crear tu usuario.');
    }
}
