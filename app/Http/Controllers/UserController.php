<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Guest;
use App\Models\Purchase;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    public function createUser(Request $request)
    {
        $credentials = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|max:14',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed'
        ]);

        $guest = Guest::where('email', $request->email)->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 2,
        ]);

        if($guest) {
            Purchase::where('guest_id', $guest->id)
            ->update([
                'user_id' => $user->id,
                'guest_id' => null,
            ]);
            $guest->delete();
        }

        return redirect()->route('videos');
    }
}
