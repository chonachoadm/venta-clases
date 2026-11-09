<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '1 mes',
            'total_price' => 25,
            'price_per_month' => 25,
            'month_amount' => 1,
        ];
    }

    /**
     * A package that lasts the given number of months at 25 USD per month.
     */
    public function months(int $months): static
    {
        return $this->state(fn () => [
            'name' => $months === 1 ? '1 mes' : "{$months} meses",
            'total_price' => 25 * $months,
            'price_per_month' => 25,
            'month_amount' => $months,
        ]);
    }
}
