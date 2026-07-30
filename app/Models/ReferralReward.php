<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reward per invited user — the payout ledger for referrals. Unique on
 * `referee_id` (and on `reference`) so a referrer is credited for a given
 * referee exactly once.
 */
class ReferralReward extends Model
{
    protected $fillable = [
        'referrer_id', 'referee_id', 'amount_minor', 'currency', 'status', 'reference', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
