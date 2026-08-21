<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'video_id',
        'thumbnail_url',
        'order_index',
    ];


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
}
