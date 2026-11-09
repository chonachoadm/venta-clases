<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Este campo no puede quedar vacío.',
            'email.email' => 'Debe utilizarse un correo con formato válido.',
            'password.required' => 'Este campo no puede quedar vacío.',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = $request->user();
            $welcome = 'Bienvenidx '.$user->name.'!';

            if ($user->must_change_password) {
                return redirect()->route('password.change')->with('success', $welcome);
            }

            if ($user->isAdmin()) {
                return redirect()->route('dashboard')->with('success', $welcome);
            }

            if ($user->hasActiveAccess()) {
                return redirect()->route('videos')->with('success', $welcome);
            }

            return redirect()->route('index')->with('success', $welcome);
        }

        return back()->withErrors([
            'email' => 'Las credenciales son incorrectas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('showLogin');
    }
}
