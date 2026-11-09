<?php

namespace Database\Factories;

use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'paypal',
            'external_id' => fake()->unique()->bothify('PAY-########'),
            'extended_access' => false,
        ];
    }
}
