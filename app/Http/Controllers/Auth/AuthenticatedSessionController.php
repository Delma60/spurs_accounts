<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SpursSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    /** Show the Spurs login screen. */
    public function create(Request $request)
    {
        // A first-party app can bounce the user here with ?return_to=<app url>.
        if ($to = $request->query('return_to')) {
            if (self::allowedReturn($to)) {
                $request->session()->put('url.intended', $to);
            }
        }

        return Inertia::render('Auth/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /** Authenticate and start the shared Spurs SSO session. */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        \App\Models\SecurityEvent::record($request->user(), 'login', $request);

        // Issue the shared SSO cookie so every first-party Spurs app is signed in.
        $cookie = SpursSession::cookie(SpursSession::issue($request->user()));

        // Back to the app/authorize flow that sent us here, else home.
        return redirect()->intended('/')->withCookie($cookie);
    }

    /** End the SSO session everywhere. */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->withCookie(SpursSession::forget());
    }

    /** Only allow redirects back to first-party Spurs hosts (no open redirect). */
    private static function allowedReturn(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === 'spurs.com.ng'
            || str_ends_with($host, '.spurs.com.ng');
    }
}
