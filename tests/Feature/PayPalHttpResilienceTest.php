<?php

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Services\PayPalPaymentService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.paypal.webhook_id' => 'WH-1', 'services.paypal.base_url' => 'https://paypal.test']);
});

test('a transient server error getting the token is retried', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::sequence()
            ->push([], 500)
            ->push(['access_token' => 'token']),
        'paypal.test/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    expect(app(PayPalPaymentService::class)->verifyWebhookSignature(Request::create('/hook', 'POST')))->toBeTrue();

    Http::assertSentCount(3);
});

test('a client error getting the token is not retried', function () {
    Http::fake(['paypal.test/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

    expect(fn () => app(PayPalPaymentService::class)->verifyWebhookSignature(Request::create('/hook', 'POST')))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

test('creating an order is never retried', function () {
    Http::fake([
        'paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'paypal.test/v2/checkout/orders' => Http::response(['message' => 'boom'], 500),
    ]);

    $purchase = new Purchase;
    $result = app(PayPalPaymentService::class)->createOneTimePayment(
        $purchase,
        new Guest(['email' => 'a@example.com']),
        new Package(['name' => 'Mensual', 'total_price' => 10]),
    );

    expect($result)->toHaveKey('error');
    Http::assertSentCount(2);
});
