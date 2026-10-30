<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Services\YouTubeImportService;
use Illuminate\Http\Request;

class AdminVideoController extends Controller
{
    public function importPlaylist(Request $request, YouTubeImportService $importer)
    {
        $validated = $request->validate([
            'playlist' => 'required|string',
            'module_id' => 'nullable|exists:modules,id',
            'new_module_name' => 'nullable|string|max:255|required_without:module_id',
        ]);

        $module = $this->resolveModule($validated);

        try {
            $count = $importer->importPlaylist($validated['playlist'], $module);
        } catch (\Throwable $e) {
            return back()->withErrors(['playlist' => $e->getMessage()]);
        }

        return back()->with('success', "Se importaron {$count} videos al módulo {$module->name}.");
    }

    public function importVideo(Request $request, YouTubeImportService $importer)
    {
        $validated = $request->validate([
            'video' => 'required|string',
            'module_id' => 'nullable|exists:modules,id',
            'new_module_name' => 'nullable|string|max:255|required_without:module_id',
        ]);

        $module = $this->resolveModule($validated);

        try {
            $importer->importVideo($validated['video'], $module);
        } catch (\Throwable $e) {
            return back()->withErrors(['video' => $e->getMessage()]);
        }

        return back()->with('success', "Video importado al módulo {$module->name}.");
    }

    /**
     * @param  array{module_id?: ?int, new_module_name?: ?string}  $validated
     */
    private function resolveModule(array $validated): Module
    {
        if (! empty($validated['module_id'])) {
            return Module::findOrFail($validated['module_id']);
        }

        return Module::create(['name' => $validated['new_module_name']]);
    }
}
