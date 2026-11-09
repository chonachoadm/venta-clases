<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filter === 'active', function ($query) {
                $query->where(fn ($query) => $query->whereHas('activeSubscription')->orWhereHas('activePurchase'));
            })
            ->when($request->filter === 'inactive', function ($query) {
                $query->whereDoesntHave('activeSubscription')->whereDoesntHave('activePurchase');
            })
            ->get();
        $totalVideos = Video::count();

        return view('admin.users.index', compact('users', 'totalVideos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|regex:/^[0-9+\s()-]+$/',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:1,2',
        ], [
            'name.required' => 'Este campo es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Este campo es obligatorio.',
            'email.unique' => 'Ya tenemos un usuario con este correo.',
            'phone.required' => 'Este campo es obligatorio.',
            'phone.regex' => 'El formato de número de teléfono es incorrecto.',
            'password.required' => 'Este campo es obligatorio.',
            'password.min' => 'La contraseña debe tener 6 caracteres o más.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'role.required' => 'Este campo es obligatorio.',
            'role.in' => 'El rol elegido no es válido.',
        ]);

        User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'],
            'password' => Hash::make($validatedData['password']),
            'role' => (int) $validatedData['role'],
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado exitósamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        $totalVideos = Video::count();
        $activeSubscription = $user->activeSubscription()->with('package')->latest()->first();
        $activePurchase = $user->activePurchase()->with('package')->latest()->first();
        $phoneNumber = preg_replace('/\D/', '', $user->phone);

        return view('admin.users.show', compact('user', 'totalVideos', 'activeSubscription', 'activePurchase', 'phoneNumber'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::findOrFail($id);

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'required|string|regex:/^[0-9+\s()-]+$/',
            'role' => 'required|in:1,2',
        ], [
            'name.required' => 'Este campo es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Este campo es obligatorio.',
            'email.unique' => 'Ya tenemos un usuario con este correo.',
            'phone.required' => 'Este campo es obligatorio.',
            'phone.regex' => 'El formato de número de teléfono es incorrecto.',
            'role.required' => 'Este campo es obligatorio.',
            'role.in' => 'El rol elegido no es válido.',
        ]);

        $user->update([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'],
            'role' => (int) $validatedData['role'],
        ]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado exitósamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
