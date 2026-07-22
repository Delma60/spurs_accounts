<?php

namespace App\Models;

use App\Support\GeoIp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class SecurityEvent extends Model
{
    protected $fillable = [
        'user_id', 'type', 'ip', 'country', 'country_name', 'city',
        'device', 'risk', 'flagged', 'signals',
    ];

    protected $casts = [
        'flagged' => 'boolean',
        'signals' => 'array',
    ];

    /** Human-readable labels per event type. */
    public const LABELS = [
        'registered' => 'Account created',
        'login' => 'Signed in',
        'login_failed' => 'Failed sign-in',
        'password_changed' => 'Password changed',
        'password_reset' => 'Password reset',
        'email_verified' => 'Email verified',
        'app_connected' => 'App connected',
        'app_revoked' => 'App access removed',
        'kyc_submitted' => 'KYC submitted',
        'kyc_verified' => 'KYC verified',
        'kyc_rejected' => 'KYC rejected',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a security event, capturing IP, a short device string and the
     * resolved location. Pass $extra to attach risk data (risk, flagged, signals).
     */
    public static function record(User $user, string $type, ?Request $request = null, array $extra = []): self
    {
        $request ??= request();
        $ip = $request?->ip();
        $geo = GeoIp::lookup($ip);

        return $user->securityEvents()->create(array_merge([
            'type' => $type,
            'ip' => $ip,
            'country' => $geo['country'],
            'country_name' => $geo['country_name'],
            'city' => $geo['city'],
            'device' => self::summariseAgent($request?->userAgent()),
        ], $extra));
    }

    /** Where this event came from, e.g. "Lagos, Nigeria" or "Local network". */
    public function location(): string
    {
        return $this->city && $this->country_name
            ? "{$this->city}, {$this->country_name}"
            : ($this->country_name ?? ($this->ip && ! str_starts_with((string) $this->ip, '127.') ? 'Unknown' : 'Local network'));
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /** Public device summary, so callers (risk engine) match stored values. */
    public static function deviceLabel(?string $agent): ?string
    {
        return self::summariseAgent($agent);
    }

    /** Turn a raw user-agent into something like "Chrome on Windows". */
    private static function summariseAgent(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'device',
        };

        return "{$browser} on {$os}";
    }
}
