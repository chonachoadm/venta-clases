<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $latestVideos = Video::latest()->limit(6)->get();
        $users = User::paginate(5);
        $totalVideos = Video::count();
        $exchangeRate = Setting::get('exchange_rate', config('services.exchange_rate', 960));
        $modules = Module::all();

        return view('admin.dashboard', compact('latestVideos', 'users', 'totalVideos', 'exchangeRate', 'modules'));
    }

    public function videosTable(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $moduleId = $request->integer('module') ?: null;

        $videos = Video::with('module')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->orderBy('module_id')
            ->orderBy('order_index')
            ->paginate(12)
            ->withQueryString();

        $modules = Module::orderBy('name')->get();

        return view('admin.videos.index', compact('videos', 'modules', 'search', 'moduleId'));
    }

    public function updateExchangeRate(Request $request)
    {
        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:1',
        ]);

        Setting::set('exchange_rate', $validated['exchange_rate']);

        return back()->with('success', 'Cotización actualizada correctamente.');
    }
}
