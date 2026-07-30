<?php

namespace App\Console\Commands;

use App\Support\Referral;
use Illuminate\Console\Command;

/**
 * Reconciles referral rewards whose wallet credit didn't land the first time.
 * Idempotent — safe to run on a schedule (see routes/console.php) or by hand.
 */
class RetryReferralPayouts extends Command
{
    protected $signature = 'referrals:retry-payouts {--limit=100 : Maximum rewards to reconcile per run}';

    protected $description = 'Retry crediting referrers whose wallet payout previously failed.';

    public function handle(): int
    {
        $r = Referral::retryFailed((int) $this->option('limit'));

        $this->info("Referral payouts — attempted {$r['attempted']}, paid {$r['paid']}, still failing {$r['failed']}.");

        return self::SUCCESS;
    }
}
