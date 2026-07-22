<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\GeoIp;
use App\Support\RiskEngine;
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
            // Record the failed attempt against a known account so the risk
            // engine can spot brute-force bursts (unknown emails are ignored).
            if ($known = User::where('email', $credentials['email'])->first()) {
                SecurityEvent::record($known, 'login_failed', $request);
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        // Score this sign-in against the account's own history (new IP/device/
        // country, impossible travel, brute-force bursts) and store the verdict.
        $user = $request->user();
        $ip = $request->ip();
        $geo = GeoIp::lookup($ip);
        $risk = RiskEngine::assess($user, (string) $ip, $geo['country'], SecurityEvent::deviceLabel($request->userAgent()));
        SecurityEvent::record($user, 'login', $request, [
            'risk' => $risk['score'],
            'flagged' => $risk['flagged'],
            'signals' => $risk['signals'],
        ]);

        // Extreme risk + admin opted into blocking: stop the session here.
        if ($risk['blocked']) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This sign-in was blocked for security reasons. Please contact support.',
            ]);
        }

        // Refresh the account's slow-moving trust score off the updated history.
        \App\Support\TrustScore::refresh($user);

        // Issue the shared SSO cookie so every first-party Spurs app is signed in.
        $cookie = SpursSession::cookie(SpursSession::issue($user));

        // Back to the app that sent us here, else home.
        $target = $request->session()->pull('url.intended', '/');

        // This POST comes from Inertia (XHR). Inertia can't follow a plain 302 to
        // another origin — it needs a hard visit (409 + X-Inertia-Location), or
        // the browser silently ends up back on this app.
        if (self::isExternal($request, $target)) {
            // Inertia::location() returns a bare 409 Symfony response (no
            // ->withCookie()), so attach the SSO cookie to its headers directly.
            $response = Inertia::location($target);
            $response->headers->setCookie($cookie);

            return $response;
        }

        return redirect($target)->withCookie($cookie);
    }

    /**
     * True when the target is a different *origin* — scheme, host AND port.
     *
     * Port matters: in development every Spurs app shares the 127.0.0.1 host and
     * differs only by port, so comparing hosts alone would treat wallet:3200 as
     * same-origin, emit a plain 302, and Inertia's XHR would follow it
     * cross-origin and die with a CORS error.
     */
    private static function isExternal(Request $request, string $target): bool
    {
        $parts = parse_url($target);
        if (empty($parts['host'])) {
            return false; // relative path — same app
        }

        $scheme = $parts['scheme'] ?? $request->getScheme();
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port !== $request->getSchemeAndHttpHost();
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
