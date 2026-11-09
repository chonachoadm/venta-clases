<?php

use App\Models\Guest;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config([
        'services.paypal.webhook_id' => 'WH-1',
        'services.paypal.base_url' => 'https://paypal.test',
        'services.mercadopago.webhook_secret' => 'test-secret',
    ]);
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    $this->package = Package::create([
        'name' => 'Mensual',
        'total_price' => 100,
        'price_per_month' => 100,
        'month_amount' => 1,
    ]);
});

function paypalSubscription(Package $package, array $attributes = []): Subscription
{
    return Subscription::create($attributes + [
        'package_id' => $package->id,
        'provider' => 'paypal',
        'status' => 'pending',
    ]);
}

test('a cancelled event without an id does not touch any subscription', function () {
    $pending = paypalSubscription($this->package, ['external_id' => null]);

    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
        'resource' => [],
    ])->assertOk();

    expect($pending->refresh()->status)->toBe('pending');
});

test('a cancelled event cancels the matching live subscription only', function () {
    $active = paypalSubscription($this->package, ['external_id' => 'I-1', 'status' => 'active']);
    $expired = paypalSubscription($this->package, ['external_id' => 'I-2', 'status' => 'expired']);
    $other = paypalSubscription($this->package, ['external_id' => 'I-3', 'status' => 'active']);

    foreach (['I-1', 'I-2'] as $id) {
        $this->postJson(route('payment.paypal-webhook'), [
            'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
            'resource' => ['id' => $id],
        ])->assertOk();
    }

    expect($active->refresh()->status)->toBe('cancelled')
        ->and($expired->refresh()->status)->toBe('expired')
        ->and($other->refresh()->status)->toBe('active');
});

test('an activated event without an id is ignored', function () {
    $guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
    $subscription = paypalSubscription($this->package, ['guest_id' => $guest->id]);

    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
        'resource' => ['custom_id' => (string) $subscription->id],
    ])->assertOk();

    expect($subscription->refresh()->status)->toBe('pending');
});

test('an activated event activates the subscription', function () {
    $guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
    $subscription = paypalSubscription($this->package, ['guest_id' => $guest->id]);

    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
        'resource' => ['custom_id' => (string) $subscription->id, 'id' => 'I-9'],
    ])->assertOk();

    expect($subscription->refresh()->status)->toBe('active')
        ->and($subscription->external_id)->toBe('I-9');
});

test('a mercado pago subscription webhook without data does not crash', function () {
    $this->postJson(
        route('webhooks.mercadopago'),
        ['type' => 'subscription_preapproval'],
        mercadoPagoSignedHeaders('', 'test-secret'),
    )->assertOk()->assertJson(['error' => 'missing id']);
});
