<?php

use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginPayload(User $user): array
{
    return ['email' => $user->email, 'password' => 'password'];
}

function memberPackage(): Package
{
    return Package::create([
        'name' => 'Mensual',
        'total_price' => 100,
        'price_per_month' => 100,
        'month_amount' => 1,
    ]);
}

test('admin is redirected to the dashboard after login', function () {
    $admin = User::factory()->create(['role' => 1]);

    $this->post(route('login'), loginPayload($admin))->assertRedirect(route('dashboard'));
});

test('user with an active subscription is redirected to videos', function () {
    $user = User::factory()->create(['role' => 2]);
    Subscription::create([
        'user_id' => $user->id,
        'package_id' => memberPackage()->id,
        'provider' => 'paypal',
        'status' => 'active',
        'expires_at' => now()->addMonth(),
    ]);

    $this->post(route('login'), loginPayload($user))->assertRedirect(route('videos'));
});

test('user with an active one-time purchase is redirected to videos', function () {
    $user = User::factory()->create(['role' => 2]);
    Purchase::create([
        'user_id' => $user->id,
        'package_id' => memberPackage()->id,
        'provider' => 'paypal',
        'status' => 'active',
        'expires_at' => now()->addMonth(),
    ]);

    $this->post(route('login'), loginPayload($user))->assertRedirect(route('videos'));
});

test('user without active access is redirected to the home page', function () {
    $user = User::factory()->create(['role' => 2]);
    Subscription::create([
        'user_id' => $user->id,
        'package_id' => memberPackage()->id,
        'provider' => 'paypal',
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    $this->post(route('login'), loginPayload($user))->assertRedirect(route('index'));
});

test('invalid credentials return an error', function () {
    $user = User::factory()->create(['role' => 2]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login is throttled after five failed attempts for the same email', function () {
    $user = User::factory()->create(['role' => 2]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    $this->post(route('login'), loginPayload($user))->assertStatus(429);
    $this->assertGuest();
});

test('throttling one email does not block another', function () {
    $blocked = User::factory()->create(['role' => 2]);
    $other = User::factory()->create(['role' => 2]);

    foreach (range(1, 6) as $attempt) {
        $this->post(route('login'), ['email' => $blocked->email, 'password' => 'wrong']);
    }

    $this->post(route('login'), loginPayload($other))->assertRedirect(route('index'));
});

test('checkout routes are throttled per ip', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post(route('checkout.confirmation'), [])->assertSessionHasErrors();
    }

    $this->post(route('checkout.confirmation'), [])->assertStatus(429);
});
