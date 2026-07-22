<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisteredUserController extends Controller
{
    /** Show the create-account screen. */
    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    /** Register a new Spurs account and sign in. */
    public function store(Request $request)
    {
        // Respect the platform setting that can close public registration.
        abort_unless(\App\Support\Settings::get('registration.allow_signups'), 403, 'Registration is currently closed.');

        $minLength = (int) \App\Support\Settings::get('registration.min_password_length');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min($minLength)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Grant the platform's default role(s) so every new account has a
        // baseline identity that travels across all of Spurs Cloud.
        $user->syncRoles(\App\Models\Role::defaultRoleNames());

        event(new Registered($user));

        Auth::login($user, true);
        $request->session()->regenerate();
        \App\Models\SecurityEvent::record($user, 'registered', $request);

        // Continue the OAuth authorize flow if we interrupted one, else home.
        return redirect()->intended('/');
    }
}
