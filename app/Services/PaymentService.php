<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;

abstract class PaymentService
{
    /**
     * Create a payment order/preference for the given purchase.
     *
     * @return array{redirect_url: string, order_id: string}
     */
    abstract public function createSubscription(Subscription $subscription, Guest $guest, Package $package): array;

    /**
     * Create a one-time (non-recurring) payment order for the given purchase.
     *
     * @return array{redirect_url: string, order_id: string}
     */
    abstract public function createOneTimePayment(Purchase $purchase, Guest $guest, Package $package): array;

    /**
     * Cancel an active recurring subscription with the provider.
     */
    abstract public function cancelSubscriptionService(Subscription $subscription): bool;

    /**
     * Get provider name.
     */
    abstract public function getProvider(): string;
}
