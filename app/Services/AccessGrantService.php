<?php

namespace App\Services;

use App\Mail\FirstAccess;
use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccessGrantService
{
    /** Days before the current expiry from which a charge counts as a renewal. */
    private const RENEWAL_WINDOW_DAYS = 7;

    /**
     * Turn a guest into a full User account. A new account gets an unknown random password and a
     * set-password link by email, so no password is ever sent.
     *
     * The guest's other pending orders are moved to the user before the guest is deleted
     * (the foreign keys would otherwise null them out and a later payment could not be matched to anyone).
     */
    public function grantAccess(Guest $guest, Package $package): User
    {
        return DB::transaction(function () use ($guest) {
            $user = User::firstOrCreate(
                ['email' => $guest->email],
                [
                    'name' => $guest->name,
                    'phone' => $guest->phone,
                    'password' => Hash::make(Str::random(40)),
                    'role' => 2,
                    'must_change_password' => true,
                ]
            );

            if ($user->wasRecentlyCreated) {
                $setPasswordUrl = route('password.reset', [
                    'token' => Password::broker()->createToken($user),
                    'email' => $user->email,
                ]);

                Mail::to('chonacholopez99@gmail.com')->queue((new FirstAccess($user, $setPasswordUrl))->afterCommit());
                // Mail::to($user->email)->queue((new FirstAccess($user, $setPasswordUrl))->afterCommit());
            }

            Subscription::where('guest_id', $guest->id)->update(['user_id' => $user->id, 'guest_id' => null]);
            Purchase::where('guest_id', $guest->id)->update(['user_id' => $user->id, 'guest_id' => null]);

            $guest->delete();

            return $user;
        });
    }

    /**
     * Activate a pending subscription (idempotent — safe to call more than once for the same event).
     */
    public function activateSubscription(Subscription $subscription, string $externalId): void
    {
        $this->activate($subscription, $externalId);
    }

    /**
     * Activate a pending one-time purchase (idempotent — safe to call more than once for the same event).
     */
    public function activatePurchase(Purchase $purchase, string $externalId): void
    {
        $this->activate($purchase, $externalId);
    }

    /**
     * Record an approved charge of a subscription and extend access when it is a renewal.
     *
     * A charge made inside the renewal window before the current expiry, or after it, is a renewal and adds the
     * package period. Any other charge is the one that opened the subscription, which activation already covers.
     * A charge that arrives before the activation event activates the subscription itself.
     * Idempotent per provider payment id.
     */
    public function registerSubscriptionPayment(Subscription $subscription, string $paymentId): void
    {
        DB::transaction(function () use ($subscription, $paymentId) {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            $alreadyRecorded = SubscriptionPayment::where('provider', $subscription->provider)
                ->where('external_id', $paymentId)
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            $extended = false;

            if ($subscription->status === 'pending') {
                $this->activateSubscription($subscription, (string) $subscription->external_id);

                if ($subscription->refresh()->status === 'pending') {
                    return;
                }
            } elseif ($subscription->status === 'cancelled') {
                // A cancelled subscription is never extended: a charge that was already in flight needs a manual refund.
                Log::warning('Payment received for a cancelled subscription, access not extended', [
                    'id' => $subscription->id,
                    'payment_id' => $paymentId,
                ]);
            } else {
                $months = $subscription->package?->month_amount;

                if (! $months) {
                    Log::error('Renewal skipped, subscription has no package', ['id' => $subscription->id]);

                    return;
                }

                $expiresAt = $subscription->expires_at;
                $extended = ! $expiresAt || now()->gte($expiresAt->copy()->subDays(self::RENEWAL_WINDOW_DAYS));

                if ($extended) {
                    $base = $expiresAt && $expiresAt->isFuture() ? $expiresAt : now();

                    $subscription->update([
                        'expires_at' => $base->copy()->addMonths($months),
                        'status' => 'active',
                    ]);
                }
            }

            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'provider' => $subscription->provider,
                'external_id' => $paymentId,
                'extended_access' => $extended,
            ]);
        });
    }

    private function activate(Subscription|Purchase $order, string $externalId): void
    {
        DB::transaction(function () use ($order, $externalId) {
            // Re-read under lock so concurrent or repeated webhook deliveries see the committed status.
            $order = $order->newQuery()->lockForUpdate()->findOrFail($order->getKey());

            if ($order->status !== 'pending') {
                Log::info('Activation skipped, order is not pending', [
                    'order' => $order::class,
                    'id' => $order->id,
                    'status' => $order->status,
                ]);

                return;
            }

            if (! $order->package) {
                Log::error('Activation skipped, order has no package', ['order' => $order::class, 'id' => $order->id]);

                return;
            }

            $user = $order->user ?? ($order->guest ? $this->grantAccess($order->guest, $order->package) : null);

            if (! $user) {
                Log::error('Activation skipped, order has neither user nor guest', ['order' => $order::class, 'id' => $order->id]);

                return;
            }

            $order->update([
                'status' => 'active',
                'external_id' => $externalId,
                'expires_at' => now()->addMonths($order->package->month_amount),
                'guest_id' => null,
                'user_id' => $user->id,
            ]);
        });
    }
}
