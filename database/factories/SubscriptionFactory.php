<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'provider' => 'paypal',
            'external_id' => null,
            'status' => 'pending',
            'expires_at' => null,
        ];
    }

    /**
     * Paid and running, owned by a user.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory(),
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);
    }

    /**
     * Cancelled by the admin or the customer but still within the paid period.
     */
    public function cancelled(): static
    {
        return $this->active()->state(fn () => ['status' => 'cancelled']);
    }

    /**
     * Past its period.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory(),
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Waiting for the payment, owned by a guest that has no account yet.
     */
    public function forGuest(): static
    {
        return $this->state(fn () => ['guest_id' => Guest::factory()]);
    }

    public function provider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }
}
