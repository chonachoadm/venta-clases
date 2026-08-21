<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class VideoController extends Controller
{
    public function indexGuest()
    {
        $videos = Video::all();

        return view('guest.videos.index', compact('videos'));
    }

    public function indexUser()
    {
        $user = Auth::user();
        $videos = Video::all();

        $user->watchedVideos()->syncWithoutDetaching($videos->pluck('id'));

        $videos = $user->watchedVideos()->get();

        return view('user.videos.index', compact('videos'));
    }

    public function showGuest(Video $video)
    {
        return view('guest.videos.show', compact('video'));
    }

    public function showUser(Video $video)
    {
        $user = Auth::user();

        $user->watchedVideos()->syncWithoutDetaching([$video->id]);

        $video = $user->watchedVideos()->find($video->id);

        return view('user.videos.show', compact('video'));
    }
    public function showAdmin(Video $video)
    {
        $nonViewers = $video->nonViewers()->get();

        return view('admin.videos.show', compact('video', 'nonViewers'));
    }

    public function updateWatched(Video $video)
    {
        $watched = request()->boolean('watched');

        Auth::user()->watchedVideos()->updateExistingPivot($video->id, [
            'watched_at' => $watched ? now() : null,
        ]);

        return redirect()->route('videos')->with('success', 'Clase completada exitósamente.');
    }
}
