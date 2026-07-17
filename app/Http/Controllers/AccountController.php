<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountController extends Controller
{
    /** The "My Account" dashboard (/me). */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Account/Home', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at?->format('M j, Y'),
            ],
            'connectedApps' => $this->connectedApps($request),
        ]);
    }

    /** Update the user's personal info. */
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return back();
    }

    /** Change the signed-in user's password. */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back();
    }

    /** Revoke a connected app's access (all its tokens for this user). */
    public function revokeApp(Request $request, string $clientId)
    {
        $request->user()->tokens()
            ->where('client_id', $clientId)
            ->update(['revoked' => true]);

        return back();
    }

    /** Apps the user has authorized (grouped from their live access tokens). */
    private function connectedApps(Request $request): array
    {
        return $request->user()->tokens()
            ->where('revoked', false)
            ->with('client')
            ->get()
            ->filter(fn ($token) => $token->client !== null)
            ->groupBy('client_id')
            ->map(fn ($tokens) => [
                'client_id' => (string) $tokens->first()->client_id,
                'name' => $tokens->first()->client->name,
                'scopes' => collect($tokens->pluck('scopes'))->flatten()->unique()->values(),
                'authorized_at' => $tokens->min('created_at')?->format('M j, Y'),
            ])
            ->values()
            ->all();
    }
}
