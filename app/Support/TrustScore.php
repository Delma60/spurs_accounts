<?php

namespace App\Support;

use App\Models\SecurityEvent;
use App\Models\User;

/**
 * Computes an account's trust score (0–100) — a slow-moving reputation drawn
 * from verification, tenure and consistent, low-risk activity. Where RiskEngine
 * scores a single sign-in for fraud, TrustScore judges the account overall, and
 * is designed to feed future credit / loan decisions.
 */
class TrustScore
{
    /**
     * @return array{score:int, band:string, factors:array<int,array{code:string,label:string,delta:int}>}
     */
    public static function for(User $user): array
    {
        $factors = [];
        $add = function (string $code, string $label, int $delta) use (&$factors) {
            if ($delta !== 0) {
                $factors[] = ['code' => $code, 'label' => $label, 'delta' => $delta];
            }
        };

        // Baseline every account starts from.
        $score = 50;
        $add('baseline', 'New account baseline', 50);

        // Verified email — a real, reachable owner.
        if ($user->hasVerifiedEmail()) {
            $score += 15;
            $add('verified', 'Email verified', 15);
        }

        // Tenure — older accounts are more trustworthy (up to +15).
        $months = $user->created_at ? (int) $user->created_at->diffInMonths(now()) : 0;
        $tenure = min($months, 15);
        $score += $tenure;
        $add('tenure', "Account age ({$months} mo)", $tenure);

        $logins = $user->securityEvents()->where('type', 'login')->get(['country', 'device']);

        // Activity — a history of successful sign-ins (up to +10).
        $activity = min(intdiv($logins->count(), 2), 10);
        $score += $activity;
        $add('activity', "{$logins->count()} successful sign-ins", $activity);

        // Consistency — few distinct countries / devices signals a stable owner.
        if ($logins->isNotEmpty()) {
            $countries = $logins->pluck('country')->filter()->unique()->count();
            if ($countries <= 1) { $score += 10; $add('location_stable', 'Consistent location', 10); }
            elseif ($countries >= 4) { $score -= 5; $add('location_spread', 'Signs in from many countries', -5); }

            $devices = $logins->pluck('device')->filter()->unique()->count();
            if ($devices <= 2) { $score += 5; $add('device_stable', 'Consistent devices', 5); }
        }

        // Recent flagged sign-ins hurt trust (up to −30).
        $flagged = $user->securityEvents()->where('flagged', true)->where('created_at', '>=', now()->subDays(90))->count();
        if ($flagged > 0) { $d = -min($flagged * 10, 30); $score += $d; $add('flagged', "{$flagged} flagged sign-in(s)", $d); }

        // Recent failed sign-ins hurt trust (up to −10).
        $failed = $user->securityEvents()->where('type', 'login_failed')->where('created_at', '>=', now()->subDays(30))->count();
        if ($failed > 0) { $d = -min($failed, 10); $score += $d; $add('failed', "{$failed} failed sign-in(s)", $d); }

        $score = max(0, min(100, $score));

        return ['score' => $score, 'band' => self::band($score), 'factors' => $factors];
    }

    /** Recompute and persist a snapshot on the user. */
    public static function refresh(User $user): int
    {
        $t = self::for($user);
        $user->forceFill(['trust_score' => $t['score'], 'trust_updated_at' => now()])->save();

        return $t['score'];
    }

    public static function band(int $score): string
    {
        // Thresholds are admin-tunable (Settings → Trust score).
        return match (true) {
            $score >= (int) Settings::get('trust.band_excellent') => 'excellent',
            $score >= (int) Settings::get('trust.band_good') => 'good',
            $score >= (int) Settings::get('trust.band_fair') => 'fair',
            $score >= (int) Settings::get('trust.band_poor') => 'poor',
            default => 'at risk',
        };
    }
}
