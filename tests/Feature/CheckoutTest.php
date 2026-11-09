<?php

use App\Models\Guest;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->package = Package::create([
        'name' => 'Mensual',
        'total_price' => 100,
        'price_per_month' => 100,
        'month_amount' => 1,
    ]);
    $this->guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
});

test('paying without a checkout session sends the visitor back to checkout with a message', function () {
    foreach ([
        'checkout.mercadopago.subscription',
        'checkout.mercadopago.onetime',
        'checkout.paypal.subscription',
        'checkout.paypal.onetime',
    ] as $routeName) {
        $this->post(route($routeName))
            ->assertRedirect(route('checkout'))
            ->assertSessionHas('error');
    }

    expect(Subscription::count())->toBe(0);
});

test('a session pointing to a deleted package also goes back to checkout', function () {
    $this->withSession(['checkout.guest_id' => $this->guest->id, 'checkout.package_id' => 999])
        ->post(route('checkout.paypal.onetime'))
        ->assertRedirect(route('checkout'));
});

test('the checkout page shows the session error', function () {
    $this->withSession(['error' => 'Tu sesión expiró.'])
        ->get(route('checkout'))
        ->assertSee('Tu sesión expiró.');
});

test('confirming again with the same email refreshes the guest name and phone', function () {
    $this->post(route('checkout.confirmation'), [
        'package_id' => $this->package->id,
        'name' => 'Ana María',
        'email' => 'ana@example.com',
        'phone' => '999888',
    ])->assertOk();

    expect(Guest::count())->toBe(1)
        ->and($this->guest->refresh()->name)->toBe('Ana María')
        ->and($this->guest->phone)->toBe('999888');
});

test('a paypal subscription for a package without a provisioned plan fails gracefully', function () {
    $this->withSession(['checkout.guest_id' => $this->guest->id, 'checkout.package_id' => $this->package->id])
        ->post(route('checkout.paypal.subscription'))
        ->assertRedirect(route('index'))
        ->assertSessionHas('error');
});

test('a mercado pago subscription redirects to the provider checkout', function () {
    config(['services.mercadopago.access_token' => 'token']);
    Http::fake(['api.mercadopago.com/preapproval' => Http::response(['id' => 'pre-1', 'init_point' => 'https://mp.test/pay'])]);

    $this->withSession(['checkout.guest_id' => $this->guest->id, 'checkout.package_id' => $this->package->id])
        ->post(route('checkout.mercadopago.subscription'))
        ->assertRedirect('https://mp.test/pay');

    expect(Subscription::first()->external_id)->toBe('pre-1');
});
