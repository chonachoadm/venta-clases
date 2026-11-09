<?php

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\AccessGrantService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());
    config([
        'services.mercadopago.access_token' => 'token',
        'services.mercadopago.webhook_secret' => 'test-secret',
        'services.paypal.webhook_id' => 'WH-1',
        'services.paypal.base_url' => 'https://paypal.test',
    ]);

    $this->package = Package::create(['name' => 'Mensual', 'total_price' => 100, 'price_per_month' => 100, 'month_amount' => 1]);
    $this->service = new AccessGrantService;
});

function runningSubscription(Package $package, CarbonInterface $expiresAt, string $provider = 'paypal', string $externalId = 'I-1', string $status = 'active'): Subscription
{
    $user = User::factory()->create(['role' => 2]);

    return Subscription::create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'provider' => $provider,
        'external_id' => $externalId,
        'status' => $status,
        'expires_at' => $expiresAt,
    ]);
}

test('a charge close to the expiry renews the access from the current expiry', function () {
    $expiresAt = now()->addDays(3);
    $subscription = runningSubscription($this->package, $expiresAt);

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');

    expect($subscription->refresh()->expires_at->equalTo($expiresAt->copy()->addMonth()))->toBeTrue()
        ->and($subscription->status)->toBe('active');
    expect(SubscriptionPayment::first())
        ->external_id->toBe('PAY-2')
        ->extended_access->toBeTrue();
});

test('a renewal adds the whole package period', function () {
    $package = Package::create(['name' => 'Trimestral', 'total_price' => 300, 'price_per_month' => 100, 'month_amount' => 3]);
    $expiresAt = now()->addDays(2);
    $subscription = runningSubscription($package, $expiresAt);

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');

    expect($subscription->refresh()->expires_at->equalTo($expiresAt->copy()->addMonths(3)))->toBeTrue();
});

test('the charge that opened the subscription does not extend it again', function () {
    $expiresAt = now()->addMonth();
    $subscription = runningSubscription($this->package, $expiresAt);

    $this->service->registerSubscriptionPayment($subscription, 'PAY-1');

    expect($subscription->refresh()->expires_at->equalTo($expiresAt))->toBeTrue();
    expect(SubscriptionPayment::first()->extended_access)->toBeFalse();
});

test('the same payment is never counted twice', function () {
    $expiresAt = now()->addDays(3);
    $subscription = runningSubscription($this->package, $expiresAt);

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');
    $this->service->registerSubscriptionPayment(Subscription::find($subscription->id), 'PAY-2');

    expect($subscription->refresh()->expires_at->equalTo($expiresAt->copy()->addMonth()))->toBeTrue()
        ->and(SubscriptionPayment::count())->toBe(1);
});

test('a second charge right after a renewal does not extend again', function () {
    $subscription = runningSubscription($this->package, now()->addDays(3));

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');
    $renewedUntil = $subscription->refresh()->expires_at->copy();

    $this->service->registerSubscriptionPayment($subscription, 'PAY-3');

    expect($subscription->refresh()->expires_at->equalTo($renewedUntil))->toBeTrue()
        ->and(SubscriptionPayment::count())->toBe(2);
});

test('a late charge after the access ended renews from now', function () {
    $subscription = runningSubscription($this->package, now()->subDays(2));

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');

    expect($subscription->refresh()->expires_at->equalTo(now()->addMonth()))->toBeTrue()
        ->and($subscription->status)->toBe('active');
});

test('an expired subscription becomes active again with a new charge', function () {
    $subscription = runningSubscription($this->package, now()->subDays(10), status: 'expired');

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');

    expect($subscription->refresh()->status)->toBe('active')
        ->and($subscription->expires_at->isFuture())->toBeTrue();
});

test('a cancelled subscription that still gets charged is not extended', function () {
    $expiresAt = now()->addDays(2);
    $subscription = runningSubscription($this->package, $expiresAt, status: 'cancelled');

    $this->service->registerSubscriptionPayment($subscription, 'PAY-2');

    expect($subscription->refresh()->status)->toBe('cancelled')
        ->and($subscription->expires_at->equalTo($expiresAt))->toBeTrue();
    expect(SubscriptionPayment::first()->extended_access)->toBeFalse();
});

test('a charge that arrives before the activation event activates the subscription', function () {
    $guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
    $subscription = Subscription::create([
        'guest_id' => $guest->id,
        'package_id' => $this->package->id,
        'provider' => 'paypal',
        'external_id' => 'I-1',
        'status' => 'pending',
    ]);

    $this->service->registerSubscriptionPayment($subscription, 'PAY-1');

    expect($subscription->refresh()->status)->toBe('active')
        ->and($subscription->expires_at->equalTo(now()->addMonth()))->toBeTrue()
        ->and(User::where('email', 'ana@example.com')->exists())->toBeTrue()
        ->and(SubscriptionPayment::first()->extended_access)->toBeFalse();
});

test('a paypal completed sale webhook renews the subscription', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);
    $expiresAt = now()->addDays(2);
    $subscription = runningSubscription($this->package, $expiresAt, 'paypal', 'I-77');

    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'PAYMENT.SALE.COMPLETED',
        'resource' => ['id' => 'SALE-1', 'billing_agreement_id' => 'I-77'],
    ])->assertOk();

    expect($subscription->refresh()->expires_at->equalTo($expiresAt->copy()->addMonth()))->toBeTrue();
});

test('a paypal completed sale for an unknown subscription or without ids changes nothing', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'PAYMENT.SALE.COMPLETED',
        'resource' => ['id' => 'SALE-1', 'billing_agreement_id' => 'I-unknown'],
    ])->assertOk();
    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'PAYMENT.SALE.COMPLETED',
        'resource' => [],
    ])->assertOk();

    expect(SubscriptionPayment::count())->toBe(0);
});

function authorizedPaymentWebhook($test, string $id = '555')
{
    return $test->postJson(
        route('webhooks.mercadopago', ['data.id' => $id]),
        ['type' => 'subscription_authorized_payment', 'data' => ['id' => $id]],
        mercadoPagoSignedHeaders($id, 'test-secret'),
    );
}

test('a mercado pago recurring charge renews the subscription', function () {
    $expiresAt = now()->addDays(2);
    $subscription = runningSubscription($this->package, $expiresAt, 'mercadopago', 'pre-9');
    Http::fake(['api.mercadopago.com/authorized_payments/555' => Http::response([
        'id' => 555,
        'preapproval_id' => 'pre-9',
        'status' => 'processed',
        'payment' => ['id' => 9001, 'status' => 'approved'],
    ])]);

    authorizedPaymentWebhook($this)->assertOk();

    expect($subscription->refresh()->expires_at->equalTo($expiresAt->copy()->addMonth()))->toBeTrue();
    expect(SubscriptionPayment::first()->external_id)->toBe('9001');
});

test('a processed mercado pago charge whose payment was declined does not renew', function () {
    $expiresAt = now()->addDays(2);
    $subscription = runningSubscription($this->package, $expiresAt, 'mercadopago', 'pre-9');
    Http::fake(['api.mercadopago.com/authorized_payments/555' => Http::response([
        'id' => 555,
        'preapproval_id' => 'pre-9',
        'status' => 'processed',
        'payment' => ['id' => 9001, 'status' => 'rejected'],
    ])]);

    authorizedPaymentWebhook($this)->assertOk();

    expect($subscription->refresh()->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(SubscriptionPayment::count())->toBe(0);
});

test('a mercado pago recurring charge for an unknown subscription is ignored', function () {
    Http::fake(['api.mercadopago.com/authorized_payments/555' => Http::response([
        'preapproval_id' => 'pre-unknown',
        'payment' => ['id' => 9001, 'status' => 'approved'],
    ])]);

    authorizedPaymentWebhook($this)->assertOk();

    expect(SubscriptionPayment::count())->toBe(0);
});

test('a mercado pago server error asks for a retry', function () {
    Http::fake(['api.mercadopago.com/authorized_payments/555' => Http::response([], 500)]);

    authorizedPaymentWebhook($this)->assertStatus(502);
});

test('an authorized payment id that is not numeric is never requested', function () {
    Http::fake();

    authorizedPaymentWebhook($this, '../v1/payments')->assertOk()->assertJson(['error' => 'missing id']);

    Http::assertNothingSent();
});

test('a recurring mercado pago payment never activates a one-time purchase with the same id', function () {
    $guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
    $purchase = Purchase::create(['guest_id' => $guest->id, 'package_id' => $this->package->id, 'provider' => 'mercadopago', 'status' => 'pending']);
    Http::fake(['api.mercadopago.com/v1/payments/55' => Http::response([
        'status' => 'approved',
        'operation_type' => 'recurring_payment',
        'external_reference' => (string) $purchase->id,
    ])]);

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '55']),
        ['type' => 'payment', 'data' => ['id' => '55']],
        mercadoPagoSignedHeaders('55', 'test-secret'),
    )->assertOk();

    expect($purchase->refresh()->status)->toBe('pending');
});

test('a regular mercado pago payment still activates the one-time purchase', function () {
    $guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
    $purchase = Purchase::create(['guest_id' => $guest->id, 'package_id' => $this->package->id, 'provider' => 'mercadopago', 'status' => 'pending']);
    Http::fake(['api.mercadopago.com/v1/payments/55' => Http::response([
        'status' => 'approved',
        'operation_type' => 'regular_payment',
        'external_reference' => (string) $purchase->id,
    ])]);

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '55']),
        ['type' => 'payment', 'data' => ['id' => '55']],
        mercadoPagoSignedHeaders('55', 'test-secret'),
    )->assertOk();

    expect($purchase->refresh()->status)->toBe('active');
});
