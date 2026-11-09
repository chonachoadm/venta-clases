<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Este campo no puede quedar vacío.',
            'email.email' => 'Debe utilizarse un correo con formato válido.',
        ]);

        Password::sendResetLink(['email' => $validated['email']]);

        // Same answer whether or not the email has an account, so it cannot be used to discover accounts.
        return back()->with('success', 'Si ese correo tiene una cuenta, te enviamos un enlace para elegir tu contraseña. Revisá tu bandeja de entrada.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'email.required' => 'Este campo no puede quedar vacío.',
            'email.email' => 'Debe utilizarse un correo con formato válido.',
            'password.required' => 'Este campo no puede quedar vacío.',
            'password.min' => 'La contraseña debe tener 6 caracteres o más.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $resetUser = null;

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $resetUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetUser) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'El enlace no es válido o ya venció. Pedí uno nuevo desde "¿Olvidaste tu contraseña?".']);
        }

        Auth::login($resetUser);
        $request->session()->regenerate();

        $welcome = 'Listo, tu contraseña quedó guardada. ¡Bienvenidx '.$resetUser->name.'!';

        if ($resetUser->isAdmin()) {
            return redirect()->route('dashboard')->with('success', $welcome);
        }

        return redirect()->route($resetUser->hasActiveAccess() ? 'videos' : 'index')->with('success', $welcome);
    }

    public function showChangeForm(): View
    {
        return view('auth.change-password');
    }

    public function change(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.required' => 'Este campo no puede quedar vacío.',
            'password.min' => 'La contraseña debe tener 6 caracteres o más.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        $destination = $user->isAdmin() ? 'dashboard' : ($user->hasActiveAccess() ? 'videos' : 'index');

        return redirect()->route($destination)->with('success', 'Listo, tu contraseña quedó guardada.');
    }
}
