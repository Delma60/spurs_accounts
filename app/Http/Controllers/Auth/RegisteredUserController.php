<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SpursSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisteredUserController extends Controller
{
    /** Show the create-account screen. */
    public function create(Request $request)
    {
        // A first-party app can bounce the user here with ?return_to=<app url>.
        // Capture it like the login screen does, so we can send them back after
        // sign-up instead of stranding them on the accounts app.
        if (($to = $request->query('return_to')) && self::allowedReturn($to)) {
            $request->session()->put('url.intended', $to);
        }

        // Remember a referral code from the invite link (?ref=CODE) so we can
        // still credit the referrer after the form round-trips.
        if ($ref = $request->query('ref')) {
            $request->session()->put('referral.code', (string) $ref);
        }

        return Inertia::render('Auth/Register', [
            'referral' => $request->session()->get('referral.code'),
        ]);
    }

    /** Register a new Spurs account and start the shared SSO session. */
    public function store(Request $request)
    {
        // Respect the platform setting that can close public registration.
        abort_unless(\App\Support\Settings::get('registration.allow_signups'), 403, 'Registration is currently closed.');

        $minLength = (int) \App\Support\Settings::get('registration.min_password_length');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'account_type' => ['nullable', 'in:'.implode(',', User::ACCOUNT_TYPES)],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,}$/'],
            'password' => ['required', 'confirmed', Password::min($minLength)->letters()->numbers()],
        ]);

        // Derive the account's home country + default currency from the sign-up
        // location, so a new user starts on their own currency in Wallet. Never
        // blocks or throws — falls back to the platform default.
        $country = \App\Support\GeoIp::lookup($request->ip())['country'] ?? null;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'account_type' => $data['account_type'] ?? 'personal',
            'phone' => $data['phone'],
            'country' => $country,
            'currency' => \App\Support\Currency::forCountry($country),
            'password' => Hash::make($data['password']),
        ]);

        // Grant the platform's default role(s) so every new account has a
        // baseline identity that travels across all of Spurs Cloud.
        $user->syncRoles(\App\Models\Role::defaultRoleNames());

        // Give them their own referral code, and — if they arrived via someone's
        // invite — link and reward the referrer. Best-effort: never blocks sign-up.
        \App\Support\Referral::ensureCode($user);
        $refCode = $request->input('ref') ?: $request->session()->pull('referral.code');
        \App\Support\Referral::onSignup($user, $refCode);

        event(new Registered($user));

        Auth::login($user, true);
        $request->session()->regenerate();
        \App\Models\SecurityEvent::record($user, 'registered', $request);

        // Finish sign-in exactly like login: issue the shared SSO cookie AND, when
        // the return target is another origin (an OAuth/return_to app), respond with
        // an Inertia hard-visit — a plain 302 to a different origin can't be followed
        // by Inertia's XHR, which is why registrations weren't redirecting.
        $cookie = SpursSession::cookie(SpursSession::issue($user));
        $target = $request->session()->pull('url.intended', '/');

        if (self::isExternal($request, $target)) {
            $response = Inertia::location($target);
            $response->headers->setCookie($cookie);

            return $response;
        }

        return redirect($target)->withCookie($cookie);
    }

    /** True when the target is a different origin (scheme, host AND port). */
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
