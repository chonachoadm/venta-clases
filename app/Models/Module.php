<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}
