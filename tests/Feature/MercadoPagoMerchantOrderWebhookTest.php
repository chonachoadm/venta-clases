<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.mercadopago.webhook_secret' => 'test-secret']);
});

test('merchant order with a foreign host never receives the access token', function () {
    Http::fake();

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '123']),
        ['topic' => 'merchant_order', 'resource' => 'https://attacker.example/merchant_orders/123'],
        mercadoPagoSignedHeaders('123', 'test-secret'),
    )->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.mercadopago.com/merchant_orders/123');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'attacker.example'));
});

test('merchant order with a non merchant order resource is rejected without any request', function () {
    Http::fake();

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '123']),
        ['topic' => 'merchant_order', 'resource' => 'https://attacker.example/steal?id=1'],
        mercadoPagoSignedHeaders('123', 'test-secret'),
    )->assertStatus(400);

    Http::assertNothingSent();
});

test('merchant order without resource is rejected', function () {
    Http::fake();

    $this->postJson(
        route('webhooks.mercadopago', ['data.id' => '123']),
        ['topic' => 'merchant_order'],
        mercadoPagoSignedHeaders('123', 'test-secret'),
    )->assertStatus(400);

    Http::assertNothingSent();
});
