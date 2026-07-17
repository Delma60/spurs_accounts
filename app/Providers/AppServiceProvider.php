<?php

namespace App\Providers;

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
        // OpenID Connect scopes advertised by the /userinfo + discovery layer.
        Passport::tokensCan([
            'openid' => 'Verify your identity',
            'profile' => 'See your name and profile info',
            'email' => 'See your email address',
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
            ]);
        });
    }
}
