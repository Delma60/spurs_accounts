<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    protected $fillable = [
        'user_id', 'level', 'status', 'id_type', 'id_masked', 'id_hash',
        'full_name', 'date_of_birth', 'phone', 'address', 'state', 'country',
        'document_ref', 'selfie_ref', 'address_proof_ref', 'address_proof_type', 'provider', 'provider_ref',
        'rejection_reason', 'reviewed_by', 'submitted_at', 'reviewed_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'date_of_birth' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** Nigerian national IDs we accept for tier 1. */
    public const NATIONAL_IDS = ['bvn', 'nin'];

    public const ID_TYPES = [
        'bvn' => 'Bank Verification Number (BVN)',
        'nin' => 'National Identity Number (NIN)',
        'passport' => 'International Passport',
        'drivers_license' => "Driver's Licence",
        'voters_card' => "Voter's Card",
    ];

    public const TIERS = [
        0 => 'Unverified',
        1 => 'Tier 1 — BVN/NIN verified',
        2 => 'Tier 2 — ID document + selfie',
        3 => 'Tier 3 — Proof of address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Hash a national ID so we can spot reuse without ever storing the number. */
    public static function hashId(string $type, string $number): string
    {
        return hash('sha256', strtolower($type).':'.preg_replace('/\D/', '', $number));
    }

    public static function mask(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    /** Other accounts that submitted the same national ID — an account-farming signal. */
    public function duplicates()
    {
        return static::where('id_hash', $this->id_hash)
            ->whereNotNull('id_hash')
            ->where('user_id', '!=', $this->user_id)
            ->get();
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->level] ?? 'Unverified';
    }
}
