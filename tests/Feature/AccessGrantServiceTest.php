<?php

use App\Mail\FirstAccess;
use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccessGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->package = Package::create([
        'name' => 'Trimestral',
        'total_price' => 300,
        'price_per_month' => 100,
        'month_amount' => 3,
    ]);
    $this->guest = Guest::create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '123456']);
    $this->service = new AccessGrantService;
});

function pendingSubscription(Guest $guest, Package $package): Subscription
{
    return Subscription::create([
        'guest_id' => $guest->id,
        'package_id' => $package->id,
        'provider' => 'paypal',
        'status' => 'pending',
    ]);
}

function pendingPurchase(Guest $guest, Package $package): Purchase
{
    return Purchase::create([
        'guest_id' => $guest->id,
        'package_id' => $package->id,
        'provider' => 'mercadopago',
        'status' => 'pending',
    ]);
}

test('activating a subscription creates the user, queues one welcome mail and removes the guest', function () {
    $subscription = pendingSubscription($this->guest, $this->package);

    $this->service->activateSubscription($subscription, 'I-123');

    $user = User::where('email', 'ana@example.com')->firstOrFail();
    $subscription->refresh();

    expect($subscription->status)->toBe('active')
        ->and($subscription->external_id)->toBe('I-123')
        ->and($subscription->user_id)->toBe($user->id)
        ->and($subscription->guest_id)->toBeNull()
        ->and($subscription->expires_at->isSameDay(now()->addMonths(3)))->toBeTrue()
        ->and(Guest::count())->toBe(0)
        ->and($user->must_change_password)->toBeTrue();

    Mail::assertQueued(
        FirstAccess::class,
        fn (FirstAccess $mail) => $mail->hasTo('ana@example.com')
            && str_contains($mail->setPasswordUrl, '/contrasena/restablecer/')
    );
    Mail::assertQueuedCount(1);
});

test('activating a purchase works the same way', function () {
    $purchase = pendingPurchase($this->guest, $this->package);

    $this->service->activatePurchase($purchase, 'PAY-1');

    expect($purchase->refresh()->status)->toBe('active')
        ->and($purchase->user_id)->toBe(User::firstWhere('email', 'ana@example.com')->id);
    Mail::assertQueued(FirstAccess::class, 1);
});

test('a repeated delivery using a stale model does not duplicate anything', function () {
    $subscription = pendingSubscription($this->guest, $this->package);
    $stale = Subscription::find($subscription->id);

    $this->service->activateSubscription($subscription, 'I-123');
    $expiresAt = $subscription->refresh()->expires_at;

    $this->service->activateSubscription($stale, 'I-123');

    expect(User::count())->toBe(1)
        ->and($subscription->refresh()->expires_at->equalTo($expiresAt))->toBeTrue();
    Mail::assertQueued(FirstAccess::class, 1);
});

test('an existing user gets access without a welcome mail', function () {
    $user = User::factory()->create(['email' => 'ana@example.com', 'role' => 2]);
    $subscription = pendingSubscription($this->guest, $this->package);

    $this->service->activateSubscription($subscription, 'I-123');

    expect($subscription->refresh()->user_id)->toBe($user->id)
        ->and(User::count())->toBe(1)
        ->and($user->refresh()->must_change_password)->toBeFalse();
    Mail::assertNothingQueued();
});

test('another pending order of the same guest is kept and can be activated later', function () {
    $subscription = pendingSubscription($this->guest, $this->package);
    $abandoned = pendingPurchase($this->guest, $this->package);

    $this->service->activateSubscription($subscription, 'I-123');

    $user = User::firstWhere('email', 'ana@example.com');
    expect($abandoned->refresh()->user_id)->toBe($user->id)
        ->and($abandoned->guest_id)->toBeNull()
        ->and($abandoned->status)->toBe('pending');

    $this->service->activatePurchase($abandoned, 'PAY-9');

    expect($abandoned->refresh()->status)->toBe('active');
    Mail::assertQueued(FirstAccess::class, 1);
});

test('orders that are not pending are never reactivated', function () {
    $subscription = pendingSubscription($this->guest, $this->package);
    $subscription->update(['status' => 'cancelled']);

    $this->service->activateSubscription($subscription, 'I-123');

    expect($subscription->refresh()->status)->toBe('cancelled')
        ->and(User::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('an order without user or guest is skipped without failing', function () {
    $subscription = pendingSubscription($this->guest, $this->package);
    $subscription->update(['guest_id' => null]);

    $this->service->activateSubscription($subscription, 'I-123');

    expect($subscription->refresh()->status)->toBe('pending');
});
