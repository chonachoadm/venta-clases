<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Video;
use App\Models\User;
use App\Models\Purchase;

class AdminController extends Controller
{
    public function index()
    {
        $videos = Video::all();
        $users = User::all();
        $totalVideos = Video::count();

        return view('admin.dashboard', compact('videos', 'users', 'totalVideos'));
    }

    public function usersTable()
    {
        $users = User::all();
        $totalVideos = Video::count();

        return view('admin.users.index', compact('users', 'totalVideos'));
    }

    public function userShow(User $user)
    {
        $totalVideos = Video::count();
        $activePurchase = $user->activePackages()->latest()->first();

        return view('admin.users.show', compact('user', 'totalVideos', 'activePurchase'));
    }

    public function userCreate()
    {
        return view('admin.users.create');
    }

    public function userStore(Request $request)
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

        return redirect()->route('users.table')->with('success', 'Usuario creado exitósamente.');
    }

    public function userEdit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function userUpdate(Request $request, User $user)
    {
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

    public function userDestroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.table')->with('success', 'Usuario eliminado correctamente.');
    }
}
