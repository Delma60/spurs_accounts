<?php

namespace App\Support;

use App\Models\KycProfile;
use App\Models\SecurityEvent;
use App\Models\User;

/**
 * Risk scoring for sign-ins. Compares the attempt against the account's own
 * history AND against the wider platform, which is where most real fraud shows
 * up: one device/IP farming many accounts, one BVN/NIN reused across accounts,
 * disposable emails, and dormant accounts suddenly waking up (takeover).
 *
 * Scores are 0–100. Flagging is advisory by default; blocking only happens when
 * an admin turns it on (fraud.block_high_risk) and the score is extreme, so a
 * single false positive can't lock out a legitimate user.
 */
class RiskEngine
{
    /** Throwaway inbox providers — common in bulk account creation.
     *  Admins can extend this list via the fraud.disposable_domains setting. */
    private const DISPOSABLE = [
        'mailinator.com', 'tempmail.com', 'temp-mail.org', 'guerrillamail.com', 'yopmail.com',
        '10minutemail.com', 'trashmail.com', 'sharklasers.com', 'getnada.com', 'dispostable.com',
        'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mailnesia.com', 'moakt.com',
    ];

    /**
     * @return array{score:int, flagged:bool, blocked:bool, signals:array<int,array{code:string,label:string,weight:int}>}
     */
    public static function assess(User $user, string $ip, ?string $country, ?string $device): array
    {
        $signals = [];
        $add = function (string $code, string $label, int $weight) use (&$signals) {
            $signals[] = ['code' => $code, 'label' => $label, 'weight' => $weight];
        };

        $history = $user->securityEvents()
            ->where('type', 'login')
            ->where('created_at', '>=', now()->subDays(90))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['ip', 'country', 'device', 'created_at']);

        /* ---------------- account-history signals ---------------- */

        if ($history->isEmpty()) {
            $add('first_login', 'First sign-in on this account', 10);
        } else {
            if (! $history->pluck('ip')->contains($ip)) {
                $add('new_ip', 'Sign-in from a new IP address', 20);
            }
            if ($device && ! $history->pluck('device')->contains($device)) {
                $add('new_device', 'Sign-in from an unrecognised device', 15);
            }
            if ($country && ! $history->pluck('country')->filter()->contains($country)) {
                $add('new_country', 'Sign-in from a new country', 25);
            }

            $last = $history->first();
            if ($country && $last->country && $last->country !== $country
                && $last->created_at->diffInHours(now()) < 2) {
                $add('impossible_travel', 'Country changed within 2 hours', 40);
            }

            // Account takeover often starts after a long dormancy.
            if ($last->created_at->diffInDays(now()) > 90) {
                $add('dormant_return', 'Dormant account active again after 90+ days', 15);
            }
        }

        $recentFailures = $user->securityEvents()
            ->where('type', 'login_failed')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();
        if ($recentFailures >= 5) {
            $add('failed_burst', "{$recentFailures} failed attempts in 15 min", 30);
        }

        /* ---------------- platform-wide signals ---------------- */

        // One IP behind several accounts — the classic account-farming pattern.
        $sharedIpThreshold = max(2, (int) Settings::get('fraud.shared_ip_threshold'));
        $sharedIp = SecurityEvent::where('ip', $ip)
            ->where('user_id', '!=', $user->getKey())
            ->distinct()->count('user_id');
        if ($sharedIp >= $sharedIpThreshold) {
            $add('shared_ip', "IP shared with {$sharedIp} other accounts", 30);
        }

        // Bulk sign-ups from the same address.
        $signups = SecurityEvent::where('ip', $ip)
            ->where('type', 'registered')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        if ($signups >= 3) {
            $add('signup_velocity', "{$signups} accounts created from this IP in 7 days", 25);
        }

        // Same device string across many accounts.
        if ($device) {
            $sharedDevice = SecurityEvent::where('device', $device)
                ->where('ip', $ip)
                ->where('user_id', '!=', $user->getKey())
                ->distinct()->count('user_id');
            if ($sharedDevice >= 3) {
                $add('shared_device', "Device seen on {$sharedDevice} other accounts", 20);
            }
        }

        /* ---------------- identity signals ---------------- */

        $extra = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) Settings::get('fraud.disposable_domains'))));
        $disposable = array_merge(self::DISPOSABLE, array_map('strtolower', $extra));
        $domain = strtolower(substr(strrchr((string) $user->email, '@') ?: '', 1));
        if ($domain && \in_array($domain, $disposable, true)) {
            $add('disposable_email', 'Disposable email provider', 25);
        }

        if (! $user->hasVerifiedEmail()) {
            $add('unverified_email', 'Email address never verified', 10);
        }

        // The strongest signal we have: the same BVN/NIN on more than one account.
        $kyc = $user->kyc()->first();
        if ($kyc && $kyc->id_hash) {
            $dupes = KycProfile::where('id_hash', $kyc->id_hash)
                ->where('user_id', '!=', $user->getKey())
                ->count();
            if ($dupes > 0) {
                $add('duplicate_national_id', "National ID reused on {$dupes} other account(s)", 45);
            }
        }

        $score = min(100, array_sum(array_column($signals, 'weight')));
        $threshold = (int) Settings::get('fraud.risk_flag_threshold');
        $blockOn = (bool) Settings::get('fraud.block_high_risk');
        $blockScore = max(1, (int) Settings::get('fraud.block_score'));

        return [
            'score' => $score,
            'flagged' => $score >= $threshold,
            'blocked' => $blockOn && $score >= $blockScore,
            'signals' => $signals,
        ];
    }
}
