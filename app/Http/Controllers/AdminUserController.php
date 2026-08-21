<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Video;
use App\Models\User;
use App\Models\Purchase;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

    $users = User::query()
        ->when($request->filter === 'active', function ($query) {
            $query->whereHas('activeSubscription');
        })
        ->when($request->filter === 'inactive', function ($query) {
            $query->whereDoesntHave('activeSubscription');
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
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'],
            'password' => Hash::make($validatedData['password']),
            'role' => $request->role,
        ]);

        return redirect()->route('users.index')->with('success', 'Usuario creado exitósamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::where('id', $id)->first();
        $totalVideos = Video::count();
        $activeSubscription = $user->activeSubscription()->latest()->first();
        $phoneNumber = preg_replace('/\D/', '', $user->phone);

        // return view('admin.users.show', compact('user', 'totalVideos', 'activePurchase'));
        return view('admin.users.show', compact('user', 'totalVideos', 'activeSubscription', 'phoneNumber'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::where('id', $id)->first();
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::where('id', $id)->first();
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|regex:/^[0-9+\s()-]+$/',
        ], [
            'name.required' => 'Este campo es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Este campo es obligatorio.',
            'phone.required' => 'Este campo es obligatorio.',
            'phone.regex' => 'El formato de número de teléfono es incorrecto.',
        ]);

        $user->update([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'phone' => $validatedData['phone'],
            'role' => $request->role,
        ]);

        return redirect()->route('users.table')->with('success', 'Usuario actualizado exitósamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::where('id', $id)->first();
        $user->delete();

        return redirect()->route('users.table')->with('success', 'Usuario eliminado correctamente.');
    }
}
