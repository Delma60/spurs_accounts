<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AccountController extends Controller
{
    /** Shared props every /me page needs (identity + KYC state for the nav). */
    private function shell(Request $request): array
    {
        $user = $request->user();
        $kyc = $user->kyc()->first();

        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'currency' => $user->currency,
                'email_verified' => $user->hasVerifiedEmail(),
                'created_at' => $user->created_at?->format('M j, Y'),
            ],
            'kyc' => $kyc ? ['status' => $kyc->status, 'level' => $kyc->level] : null,
        ];
    }

    /** Overview (/me). */
    public function index(Request $request)
    {
        return Inertia::render('Account/Home', [
            ...$this->shell($request),
            'appCount' => count($this->connectedApps($request)),
        ]);
    }

    /** Personal info (/me/personal). */
    public function personal(Request $request)
    {
        return Inertia::render('Account/Personal', $this->shell($request));
    }

    /** Security & sign-in (/me/security). */
    public function security(Request $request)
    {
        return Inertia::render('Account/Security', [
            ...$this->shell($request),
            'connectedApps' => $this->connectedApps($request),
            'securityEvents' => $this->securityEvents($request),
        ]);
    }

    /** Connected apps (/me/apps). */
    public function apps(Request $request)
    {
        return Inertia::render('Account/Apps', [
            ...$this->shell($request),
            'connectedApps' => $this->connectedApps($request),
        ]);
    }

    /**
     * Payments & subscriptions (/me/payments) — one place for wallet balances,
     * transactions and recurring payments, the way Google groups them.
     */
    public function payments(Request $request)
    {
        $userId = (string) $request->user()->getKey();

        return Inertia::render('Account/Payments', [
            ...$this->shell($request),
            'balances' => \App\Support\WalletClient::balances($userId),
            'transactions' => \App\Support\WalletClient::transactions($userId, 40),
            // Recurring billing appears here once the billing service exposes it.
            'subscriptions' => [],
            'connectedApps' => $this->connectedApps($request),
        ]);
    }

    /** Invite & earn (/me/referrals) — the user's code, share link and rewards. */
    public function referrals(Request $request)
    {
        $user = $request->user();
        $code = \App\Support\Referral::ensureCode($user);
        $enabled = (bool) \App\Support\Settings::get('referral.enabled');

        $rewards = \App\Models\ReferralReward::where('referrer_id', $user->getKey())
            ->with('referee:id,name,created_at')
            ->latest()
            ->get();

        $earnedMinor = (int) $rewards->where('status', 'paid')->sum('amount_minor');

        return Inertia::render('Account/Referrals', [
            ...$this->shell($request),
            'referral' => [
                'enabled' => $enabled,
                'code' => $code,
                'link' => url('/register?ref='.$code),
                'bonus' => (int) \App\Support\Settings::get('referral.bonus_amount'),
                'invited' => $rewards->count(),
                'earnedNaira' => intdiv($earnedMinor, 100),
                'people' => $rewards->map(fn ($r) => [
                    'name' => $r->referee?->name ?? 'New user',
                    'joined' => $r->referee?->created_at?->format('M j, Y'),
                    'status' => $r->status,
                    'amountNaira' => intdiv((int) $r->amount_minor, 100),
                ])->values(),
            ],
        ]);
    }

    /** Update the user's personal info. */
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,}$/'],
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
        SecurityEvent::record($request->user(), 'password_changed', $request);

        return back();
    }

    /** Revoke a connected app's access (all its tokens for this user). */
    public function revokeApp(Request $request, string $clientId)
    {
        $request->user()->tokens()
            ->where('client_id', $clientId)
            ->update(['revoked' => true]);

        SecurityEvent::record($request->user(), 'app_revoked', $request);

        return back();
    }

    /** Third-party apps the user has authorized (grouped from live access tokens).
     * First-party Spurs apps use the shared SSO cookie, not tokens, so they're
     * intentionally excluded here. */
    private function connectedApps(Request $request): array
    {
        $firstParty = config('spurs.first_party_client_ids', []);

        return $request->user()->tokens()
            ->where('revoked', false)
            ->with('client')
            ->get()
            ->filter(fn ($token) => $token->client !== null)
            ->reject(fn ($token) => in_array((string) $token->client_id, $firstParty, true))
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

    /** The user's recent security activity for the Security section. */
    private function securityEvents(Request $request): array
    {
        return $request->user()->securityEvents()
            ->limit(10)
            ->get()
            ->map(fn (SecurityEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'label' => $e->label(),
                'ip' => $e->ip,
                'device' => $e->device,
                'at' => $e->created_at?->diffForHumans(),
            ])
            ->all();
    }
}
