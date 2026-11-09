<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake();
    config(['services.mercadopago.webhook_secret' => 'test-secret']);
});

test('a correctly signed webhook is accepted', function () {
    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => 'ABC123']),
        ['type' => 'unknown'],
        mercadoPagoSignedHeaders('ABC123', 'test-secret'),
    )->assertOk()->assertJson(['ignored' => true]);
});

test('the webhook log never contains the payload', function () {
    Log::spy();

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => 'ABC123']),
        ['type' => 'unknown', 'data' => ['id' => 'ABC123'], 'payer' => ['email' => 'secret@example.com']],
        mercadoPagoSignedHeaders('ABC123', 'test-secret'),
    )->assertOk();

    Log::shouldHaveReceived('info')->with('MercadoPago webhook received', ['type' => 'unknown', 'id' => 'ABC123'])->once();
    Log::shouldNotHaveReceived('info', fn ($message, $context = []) => str_contains(json_encode($context), 'secret@example.com'));
});

test('a webhook without signature is rejected', function () {
    $this->postJson(route('webhooks.mercadopago'), ['type' => 'payment', 'data' => ['id' => '1']])
        ->assertStatus(401);

    Http::assertNothingSent();
});

test('a webhook signed with another secret is rejected', function () {
    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '1']),
        ['type' => 'payment', 'data' => ['id' => '1']],
        mercadoPagoSignedHeaders('1', 'other-secret'),
    )->assertStatus(401);

    Http::assertNothingSent();
});

test('a webhook whose data id was tampered with is rejected', function () {
    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '2']),
        ['type' => 'payment', 'data' => ['id' => '2']],
        mercadoPagoSignedHeaders('1', 'test-secret'),
    )->assertStatus(401);

    Http::assertNothingSent();
});

test('webhooks are rejected when the secret is not configured', function () {
    config(['services.mercadopago.webhook_secret' => null]);

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '1']),
        ['type' => 'payment', 'data' => ['id' => '1']],
        mercadoPagoSignedHeaders('1', ''),
    )->assertStatus(401);
});

test('paypal webhooks are rejected when the webhook id is not configured', function () {
    config(['services.paypal.webhook_id' => null]);

    $this->postJson(route('payment.paypal-webhook'), ['event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED'])
        ->assertStatus(400);

    Http::assertNothingSent();
});
