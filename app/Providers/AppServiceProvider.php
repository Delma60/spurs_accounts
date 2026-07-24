<?php

namespace App\Providers;

use App\Notifications\SpursChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route notifications tagged `spurs` through the platform mailer, so
        // auth emails match every other Spurs email and no SMTP credentials
        // live in this app.
        Notification::extend('spurs', fn () => new SpursChannel);

        // OpenID Connect scopes advertised by the /userinfo + discovery layer.
        Passport::tokensCan([
            'openid' => 'Verify your identity',
            'profile' => 'See your name and profile info',
            'email' => 'See your email address',
            'roles' => 'See your roles and permissions across Spurs Cloud',
        ]);

        // Render Passport's OAuth consent screen as a branded Inertia page
        // instead of the default Blade view, so it matches the Spurs design.
        Passport::authorizationView(function ($parameters) {
            return Inertia::render('Auth/Consent', [
                'client' => ['name' => $parameters['client']->name],
                'user' => ['name' => $parameters['user']->name, 'email' => $parameters['user']->email],
                'scopes' => collect($parameters['scopes'])->map(fn ($s) => [
                    'id' => $s->id,
                    'description' => $s->description,
                ])->values(),
                'authToken' => $parameters['authToken'],
                'state' => $parameters['request']->state,
                'clientId' => $parameters['client']->getKey(),
                'csrf' => csrf_token(),
            ]);
        });
    }
}
