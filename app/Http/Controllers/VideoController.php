<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Support\Facades\Auth;

class VideoController extends Controller
{
    public function indexGuest()
    {
        $videos = Video::orderBy('module_id')->orderBy('order_index')->paginate(12);

        return view('guest.videos.index', compact('videos'));
    }

    public function indexUser()
    {
        $user = Auth::user();
        $videos = Video::orderBy('module_id')->orderBy('order_index')->paginate(12);
        $watchedVideoIds = $user->watchedVideos()->pluck('videos.id')->all();

        return view('user.videos.index', compact('videos', 'watchedVideoIds'));
    }

    public function showGuest(Video $video)
    {
        return view('guest.videos.show', compact('video'));
    }

    public function showUser(Video $video)
    {
        $watched = Auth::user()->watchedVideos()->where('videos.id', $video->id)->exists();

        return view('user.videos.show', compact('video', 'watched'));
    }

    public function showAdmin(Video $video)
    {
        $nonViewers = $video->nonViewers()->get();

        return view('admin.videos.show', compact('video', 'nonViewers'));
    }

    public function updateWatched(Video $video)
    {
        $watched = request()->boolean('watched');
        $user = Auth::user();

        if ($watched) {
            $user->watchedVideos()->syncWithoutDetaching([
                $video->id => ['watched_at' => now()],
            ]);
        } else {
            $user->watchedVideos()->detach($video->id);
        }

        return redirect()->route('videos')->with('success', 'Clase completada exitósamente.');
    }
}
