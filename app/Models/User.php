<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function watchedVideos()
    {
        return $this->belongsToMany(Video::class)
            ->withPivot('watched_at');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'cancelled'])
            ->where('expires_at', '>', now());
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activePurchase()
    {
        return $this->hasOne(Purchase::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function isAdmin()
    {
        return $this->role === 1;
    }

    public function hasActiveAccess(): bool
    {
        return $this->activeSubscription()->exists() || $this->activePurchase()->exists();
    }

    /**
     * Share of the library this user has watched, as a whole percentage (0 when the library is empty).
     */
    public function watchedPercentage(int $totalVideos): int
    {
        if ($totalVideos === 0) {
            return 0;
        }

        return (int) round($this->watchedVideos()->whereNotNull('watched_at')->count() / $totalVideos * 100);
    }
}
