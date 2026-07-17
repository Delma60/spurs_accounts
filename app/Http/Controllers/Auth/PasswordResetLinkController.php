<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    /** Show the "forgot password" screen. */
    public function create(Request $request)
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /** Email a password reset link. */
    public function store(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always report success to avoid leaking which emails are registered.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that email is registered, a reset link is on its way.');
    }
}
