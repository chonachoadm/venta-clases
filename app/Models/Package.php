<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'total_price',
        'price_per_month',
        'month_amount',
        'mercadopago_plan_id',
        'paypal_plan_id',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * total_price (USD) converted to ARS, rounded to the nearest thousand.
     */
    public function totalPriceArs(): float
    {
        return round($this->total_price * static::exchangeRate(), -3);
    }

    /**
     * price_per_month (USD) converted to ARS, rounded to the nearest thousand.
     */
    public function pricePerMonthArs(): float
    {
        return round($this->price_per_month * static::exchangeRate(), -3);
    }

    private static function exchangeRate(): float
    {
        return (float) Setting::get('exchange_rate', config('services.exchange_rate', 960));
    }
}
