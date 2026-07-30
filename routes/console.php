<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcile any referral payouts the wallet didn't accept first time. Idempotent,
// so overlapping runs are harmless — but avoid piling up if wallet is slow.
Schedule::command('referrals:retry-payouts')->everyFifteenMinutes()->withoutOverlapping();
