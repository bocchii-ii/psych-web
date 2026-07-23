<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestSessionController extends Controller
{
    /**
     * Log in as a guest using only a display name — no email/password required.
     */
    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return to_route('home');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => Str::uuid().'@guest.local',
            'password' => Hash::make(Str::random(40)),
            'is_guest' => true,
        ]);

        Auth::login($user);

        return to_route('home');
    }
}
