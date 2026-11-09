<?php

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config([
        'services.paypal.base_url' => 'https://paypal.test',
        'services.paypal.webhook_id' => 'WH-1',
        'services.mercadopago.access_token' => 'token',
    ]);
});

function pendingPaypalPurchase(string $orderId = 'ORDER-1'): Purchase
{
    return Purchase::factory()->forGuest()->provider('paypal')->create(['external_id' => $orderId]);
}

function fakePaypalCapture(string $status = 'COMPLETED'): void
{
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v2/checkout/orders/ORDER-1/capture' => Http::response(['status' => $status]),
    ]);
}

test('the failure and pending pages send the buyer home with a message', function () {
    $this->get(route('payment.failure'))->assertRedirect(route('index'))->assertSessionHas('error');
    $this->get(route('payment.pending'))->assertRedirect(route('index'))->assertSessionHas('message');
});

test('returning from a provider without paypal parameters only shows the confirmation message', function () {
    $this->get(route('payment.success'))->assertRedirect(route('index'))->assertSessionHas('success');
});

test('a buyer approving a paypal order gets it captured and activated', function () {
    fakePaypalCapture();
    $purchase = pendingPaypalPurchase();
    $email = $purchase->guest->email;

    $this->get(route('payment.success', ['token' => 'ORDER-1', 'PayerID' => 'PAYER']))
        ->assertRedirect(route('index'))
        ->assertSessionHas('success');

    expect($purchase->refresh()->status)->toBe('active')
        ->and($purchase->expires_at->isFuture())->toBeTrue()
        ->and(User::where('email', $email)->exists())->toBeTrue();
});

test('an order that is not completed stays pending and sends the buyer to the failure page', function () {
    fakePaypalCapture('DECLINED');
    $purchase = pendingPaypalPurchase();

    $this->get(route('payment.success', ['token' => 'ORDER-1', 'PayerID' => 'PAYER']))->assertRedirect(route('payment.failure'));

    expect($purchase->refresh()->status)->toBe('pending');
});

test('an unknown paypal order goes to the failure page without calling paypal', function () {
    Http::fake();

    $this->get(route('payment.success', ['token' => 'NOPE', 'PayerID' => 'PAYER']))->assertRedirect(route('payment.failure'));

    Http::assertNothingSent();
});

test('a paypal error while capturing is handled', function () {
    Http::fake(['paypal.test/*' => Http::response(['error' => 'invalid_client'], 401)]);
    $purchase = pendingPaypalPurchase();

    $this->get(route('payment.success', ['token' => 'ORDER-1', 'PayerID' => 'PAYER']))->assertRedirect(route('payment.failure'));

    expect($purchase->refresh()->status)->toBe('pending');
});

test('returning twice for an already active order does not capture again', function () {
    Http::fake();
    $purchase = Purchase::factory()->active()->provider('paypal')->create(['external_id' => 'ORDER-1']);

    $this->get(route('payment.success', ['token' => 'ORDER-1', 'PayerID' => 'PAYER']))->assertRedirect(route('index'));

    Http::assertNothingSent();
    expect($purchase->refresh()->status)->toBe('active');
});

function paypalWebhook($test, array $payload)
{
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    return $test->postJson(route('payment.paypal-webhook'), $payload);
}

test('the paypal capture completed webhook activates the purchase', function () {
    $purchase = pendingPaypalPurchase();

    paypalWebhook($this, [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-1', 'custom_id' => (string) $purchase->id],
    ])->assertOk();

    expect($purchase->refresh()->status)->toBe('active')->and($purchase->external_id)->toBe('CAP-1');
});

test('a repeated capture webhook does not activate twice or send a second email', function () {
    $purchase = pendingPaypalPurchase();
    $payload = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAP-1', 'custom_id' => (string) $purchase->id]];

    paypalWebhook($this, $payload)->assertOk();
    $expiresAt = $purchase->refresh()->expires_at;
    paypalWebhook($this, $payload)->assertOk();

    expect($purchase->refresh()->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(User::count())->toBe(1);
    Mail::assertQueuedCount(1);
});

test('a capture webhook for an unknown purchase is acknowledged and ignored', function () {
    paypalWebhook($this, [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-1', 'custom_id' => '9999'],
    ])->assertOk();

    expect(Purchase::count())->toBe(0);
});

test('a paypal webhook that fails verification is rejected', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
    ]);
    $purchase = pendingPaypalPurchase();

    $this->postJson(route('payment.paypal-webhook'), [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-1', 'custom_id' => (string) $purchase->id],
    ])->assertStatus(400);

    expect($purchase->refresh()->status)->toBe('pending');
});

test('an unrelated paypal event is ignored', function () {
    paypalWebhook($this, ['event_type' => 'CUSTOMER.DISPUTE.CREATED', 'resource' => []])->assertOk()->assertJson(['ignored' => true]);
});

function checkoutSession(Guest $guest, Package $package): array
{
    return ['checkout.guest_id' => $guest->id, 'checkout.package_id' => $package->id];
}

test('a paypal one time checkout creates the order and sends the buyer to approve it', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v2/checkout/orders' => Http::response([
            'id' => 'ORDER-9',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve/ORDER-9']],
        ]),
    ]);
    $guest = Guest::factory()->create();
    $package = Package::factory()->months(3)->create();

    $this->withSession(checkoutSession($guest, $package))
        ->post(route('checkout.paypal.onetime'))
        ->assertRedirect('https://paypal.test/approve/ORDER-9');

    $purchase = Purchase::first();
    expect($purchase->external_id)->toBe('ORDER-9')
        ->and($purchase->status)->toBe('pending')
        ->and($purchase->guest_id)->toBe($guest->id);
    Http::assertSent(fn ($request) => $request->url() === 'https://paypal.test/v2/checkout/orders'
        && $request['purchase_units'][0]['amount']['value'] === '75.00'
        && $request['purchase_units'][0]['custom_id'] === (string) $purchase->id);
});

test('a paypal subscription checkout uses the provisioned plan', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v1/billing/subscriptions' => Http::response([
            'id' => 'I-9',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve/I-9']],
        ]),
    ]);
    $guest = Guest::factory()->create();
    $package = Package::factory()->create(['paypal_plan_id' => 'P-PLAN']);

    $this->withSession(checkoutSession($guest, $package))
        ->post(route('checkout.paypal.subscription'))
        ->assertRedirect('https://paypal.test/approve/I-9');

    expect(Subscription::first()->external_id)->toBe('I-9');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/billing/subscriptions')
        && $request['plan_id'] === 'P-PLAN'
        && $request['custom_id'] === (string) Subscription::first()->id);
});

test('a mercado pago one time checkout creates the preference with the signed webhook url', function () {
    Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'PREF-1', 'init_point' => 'https://mp.test/pay'])]);
    $guest = Guest::factory()->create();
    $package = Package::factory()->months(1)->create();

    $this->withSession(checkoutSession($guest, $package))
        ->post(route('checkout.mercadopago.onetime'))
        ->assertRedirect('https://mp.test/pay');

    $purchase = Purchase::first();
    expect($purchase->external_id)->toBe('PREF-1');
    Http::assertSent(fn ($request) => $request['external_reference'] === (string) $purchase->id
        && str_contains($request['notification_url'], 'source_news=webhooks'));
});

test('a mercado pago subscription checkout sends the signed webhook url so renewals are notified', function () {
    Http::fake(['api.mercadopago.com/preapproval' => Http::response(['id' => 'pre-1', 'init_point' => 'https://mp.test/pay'])]);
    $guest = Guest::factory()->create();
    $package = Package::factory()->months(1)->create();

    $this->withSession(checkoutSession($guest, $package))
        ->post(route('checkout.mercadopago.subscription'))
        ->assertRedirect('https://mp.test/pay');

    $subscription = Subscription::first();
    expect($subscription->external_id)->toBe('pre-1');
    Http::assertSent(fn ($request) => $request['external_reference'] === (string) $subscription->id
        && str_contains($request['notification_url'], 'source_news=webhooks'));
});

test('a provider error during checkout sends the buyer home with a generic message', function () {
    Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response(['message' => 'secret detail'], 500)]);
    $guest = Guest::factory()->create();
    $package = Package::factory()->months(1)->create();

    $this->withSession(checkoutSession($guest, $package))
        ->post(route('checkout.mercadopago.onetime'))
        ->assertSessionHas('error', 'Hubo un error, intentá de nuevo más tarde.');
});

test('an email that already has active access cannot buy again', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    Subscription::factory()->active()->create(['user_id' => $user->id]);
    $package = Package::factory()->create();

    $this->post(route('checkout.confirmation'), [
        'package_id' => $package->id,
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'phone' => '123456',
    ])->assertSessionHasErrors('email');

    expect(Guest::count())->toBe(0);
});
