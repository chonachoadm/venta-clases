<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Guest;
use App\Models\Package;

abstract class PaymentService
{
    /**
     * Create a payment order/preference for the given purchase.
     *
     * @param Purchase $purchase
     * @return array{redirect_url: string, order_id: string}
     */
    abstract public function createSubscription(Subscription $subscription, Guest $guest, Package $package): array;

    /**
     * Verify and approve a payment after successful redirect.
     *
     * @param Subscription $subscription
     * @return bool
     */
    abstract public function verifySubscription(Subscription $subscription): bool;

    /**
     * Verify webhook signature and return validated payload.
     *
     * @param array $payload
     * @param string $signature
     * @return array|null Validated payload or null if invalid
     */
    abstract public function verifyWebhook(array $payload, string $signature): ?array;

    /**
     * Get provider name.
     *
     * @return string
     */
    abstract public function getProvider(): string;
}
