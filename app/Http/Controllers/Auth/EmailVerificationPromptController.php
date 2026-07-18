<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailVerificationPromptController extends Controller
{
    /** Show the "please verify your email" notice (or move on if already verified). */
    public function __invoke(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended('/me')
            : Inertia::render('Auth/VerifyEmail', [
                'status' => $request->session()->get('status'),
                'email' => $request->user()->email,
            ]);
    }
}
