<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Cookie\CookieJar;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The Spurs single sign-on session — a shared, signed cookie readable by every
 * first-party Spurs app (baas, pay, wallet, cloud). Sign in once at accounts and
 * you're signed in everywhere; first-party apps never take an access token, so
 * they never appear under "Connected apps" (that list is third-party only).
 *
 * Token is a compact HS256 JWT signed with the shared SPURS_SESSION_SECRET.
 */
class SpursSession
{
    public static function cookieName(): string
    {
        return config('spurs.cookie', 'spurs_session');
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Mint a signed SSO token for a user. */
    public static function issue(User $user): string
    {
        $now = time();
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64(json_encode([
            'sub' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'iat' => $now,
            'exp' => $now + 60 * 60 * 24 * 7, // 7 days
        ]));
        $sig = self::b64(hash_hmac('sha256', "$header.$payload", (string) config('spurs.secret'), true));

        return "$header.$payload.$sig";
    }

    /** Build the Set-Cookie for the shared session (scoped to all Spurs apps). */
    public static function cookie(string $token): Cookie
    {
        return cookie(
            name: self::cookieName(),
            value: $token,
            minutes: 60 * 24 * 7,
            path: '/',
            domain: config('spurs.cookie_domain'),  // null in dev (host-only), .spurs.com.ng in prod
            secure: config('spurs.cookie_secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    /** The forget-cookie used on logout. */
    public static function forget(): Cookie
    {
        /** @var CookieJar $jar */
        $jar = app(CookieJar::class);

        return $jar->forget(self::cookieName(), '/', config('spurs.cookie_domain'));
    }
}
