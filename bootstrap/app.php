<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // OIDC UserInfo is a Bearer-token API call, not a form — no CSRF.
        $middleware->validateCsrfTokens(except: [
            'oauth/userinfo',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The UserInfo API must answer 401 (not redirect to login) when called
        // without a valid token. /oauth/authorize stays a browser flow that
        // redirects guests to the login screen, so scope this to userinfo only.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('oauth/userinfo')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
