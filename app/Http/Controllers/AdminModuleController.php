<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;

class AdminModuleController extends Controller
{
    public function index()
    {
        $modules = Module::withCount('videos')->get();

        // dd($modules);
        return view('admin.modules.index', compact('modules'));
    }

    public function create()
    {
        return view('admin.modules.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Module::create($validated);

        return redirect()->route('modulos.index')->with('success', 'Módulo creado exitósamente.');
    }

    public function edit(Module $module)
    {
        return view('admin.modules.edit', compact('module'));
    }

    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $module->update($validated);

        return redirect()->route('modulos.index')->with('success', 'Módulo actualizado exitósamente.');
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return redirect()->route('modulos.index')->with('success', 'Módulo eliminado correctamente.');
    }
}
