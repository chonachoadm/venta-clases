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
}
