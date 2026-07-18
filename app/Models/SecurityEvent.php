<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class SecurityEvent extends Model
{
    protected $fillable = ['user_id', 'type', 'ip', 'device'];

    /** Human-readable labels per event type. */
    public const LABELS = [
        'registered' => 'Account created',
        'login' => 'Signed in',
        'password_changed' => 'Password changed',
        'password_reset' => 'Password reset',
        'email_verified' => 'Email verified',
        'app_connected' => 'App connected',
        'app_revoked' => 'App access removed',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Record a security event for a user, capturing IP + a short device string. */
    public static function record(User $user, string $type, ?Request $request = null): self
    {
        $request ??= request();

        return $user->securityEvents()->create([
            'type' => $type,
            'ip' => $request?->ip(),
            'device' => self::summariseAgent($request?->userAgent()),
        ]);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
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
