<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Video extends Model
{
    use Searchable;
    protected $fillable = [
        'module_id',
        'title',
        'description',
        'video_id',
        'thumbnail_url',
        'order_index',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function viewers()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('watched_at');
    }

    public function nonViewers()
    {
        return User::whereDoesntHave('watchedVideos', function ($query) {
            $query->where('videos.id', $this->id);
        });
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
