<?php

namespace App\Http\Controllers;

use App\Support\SpursSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The single entry point first-party Spurs apps send unauthenticated visitors to.
 * If the user already has an accounts session we (re)issue the shared SSO cookie
 * and bounce them straight back to the app; otherwise we route them through login
 * first. Not guarded by the `guest` middleware, so signed-in users are handled.
 */
class SsoController extends Controller
{
    public function continue(Request $request)
    {
        $returnTo = $request->query('return_to');

        if (! Auth::check()) {
            return redirect('/login'.($returnTo ? '?return_to='.urlencode($returnTo) : ''));
        }

        $cookie = SpursSession::cookie(SpursSession::issue($request->user()));
        $target = ($returnTo && self::allowedReturn($returnTo)) ? $returnTo : '/me';

        return redirect()->away($target)->withCookie($cookie);
    }

    private static function allowedReturn(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === 'spurs.com.ng'
            || str_ends_with((string) $host, '.spurs.com.ng');
    }
}
