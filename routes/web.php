<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\OidcController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing: signed-in users go straight to their account (/me), guests see welcome.
Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/me');
    }

    return Inertia::render('Home', ['user' => null]);
})->name('home');

// The signed-in "My Account" area.
Route::middleware('auth')->group(function () {
    Route::get('/me', [AccountController::class, 'index'])->name('me');
    Route::put('/me/profile', [AccountController::class, 'updateProfile'])->name('me.profile');
    Route::put('/me/password', [AccountController::class, 'updatePassword'])->name('me.password');
    Route::delete('/me/apps/{clientId}', [AccountController::class, 'revokeApp'])->name('me.apps.revoke');

    // Email verification.
    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')->name('verification.send');
});

// ---- Spurs SSO (first-party shared-cookie entry point) ----
Route::get('/sso/continue', [\App\Http\Controllers\SsoController::class, 'continue'])->name('sso.continue');

// ---- OpenID Connect provider endpoints ----
Route::get('/.well-known/openid-configuration', [OidcController::class, 'configuration']);
Route::get('/oauth/jwks', [OidcController::class, 'jwks']);
Route::get('/oauth/userinfo', [OidcController::class, 'userinfo'])->middleware('auth:api');
Route::post('/oauth/userinfo', [OidcController::class, 'userinfo'])->middleware('auth:api');

// Login / register / logout (the SSO session).
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    // Password reset.
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
