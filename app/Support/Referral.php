<?php

namespace App\Support;

use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Referral rewards. Every account has a shareable code; when a new user signs up
 * with someone's code we link them and credit the referrer's wallet with the
 * admin-configured reward. Rewards are paid in NGN (the amount is set in naira)
 * and are idempotent — the ledger's unique `referee_id` guarantees one payout
 * per invited user even if this runs twice.
 *
 * The whole flow is best-effort: a wallet outage or a disabled program never
 * blocks or fails registration.
 */
class Referral
{
    /** Reward is always paid in the platform's home currency. */
    private const REWARD_ASSET = 'NGN';

    /** Give a user a unique referral code if they don't have one yet. */
    public static function ensureCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    /** The user who owns a referral code, or null (blank/unknown/self is ignored). */
    public static function referrerFor(?string $code): ?User
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        return User::where('referral_code', strtoupper($code))->first();
    }

    /**
     * Called once, right after a new account is created. Links the referee to the
     * referrer and pays the reward(s). Safe to call unconditionally — it no-ops
     * when referrals are off, the code is bad, or the reward is zero.
     */
    public static function onSignup(User $referee, ?string $code): void
    {
        if (! Settings::get('referral.enabled')) {
            return;
        }

        $referrer = self::referrerFor($code);
        if (! $referrer || $referrer->id === $referee->id) {
            return; // no code, unknown code, or self-referral
        }

        // Record who invited them (cheap, always useful even if the reward is 0).
        $referee->forceFill(['referred_by' => $referrer->id])->save();

        $amountNaira = (int) Settings::get('referral.bonus_amount');
        if ($amountNaira <= 0) {
            return;
        }
        $amountMinor = $amountNaira * 100;

        self::payReferrer($referrer, $referee, $amountMinor);

        if (Settings::get('referral.reward_referee')) {
            self::payReferee($referee, $amountMinor);
        }
    }

    /** Credit the referrer, tracked in the ledger so it can only happen once. */
    private static function payReferrer(User $referrer, User $referee, int $amountMinor): void
    {
        $reference = "referral:{$referee->id}";

        // Unique referee_id makes this the idempotency guard: if a row already
        // exists we've handled this referee before.
        $reward = ReferralReward::firstOrCreate(
            ['referee_id' => $referee->id],
            [
                'referrer_id' => $referrer->id,
                'amount_minor' => $amountMinor,
                'currency' => self::REWARD_ASSET,
                'status' => 'pending',
                'reference' => $reference,
            ],
        );

        if ($reward->status === 'paid') {
            return;
        }

        $ok = WalletClient::credit($referrer->id, self::REWARD_ASSET, $amountMinor, $reference, [
            'source' => 'referral_bonus',
            'description' => 'Referral reward',
        ]);

        $reward->forceFill([
            'status' => $ok ? 'paid' : 'failed',
            'paid_at' => $ok ? now() : null,
        ])->save();
    }

    /** Optionally reward the new user too, on their own idempotent reference. */
    private static function payReferee(User $referee, int $amountMinor): void
    {
        WalletClient::credit($referee->id, self::REWARD_ASSET, $amountMinor, "referral-welcome:{$referee->id}", [
            'source' => 'referral_bonus',
            'description' => 'Welcome referral bonus',
        ]);
    }
}
