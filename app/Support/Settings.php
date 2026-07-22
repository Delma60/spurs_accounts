<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The identity provider's live settings. Coded defaults live in SCHEMA; DB rows
 * override them. Values are read through here so a change in the admin takes
 * effect immediately (registration, session lifetime, password policy, fraud).
 */
class Settings
{
    /**
     * key => [group, label, type(bool|int|string|text), default, help, effect]
     * "effect" flags settings that are actively enforced by this app.
     */
    public const SCHEMA = [
        'registration.allow_signups' => ['Registration', 'Allow new sign-ups', 'bool', true, 'Turn off to close public registration.', true],
        'registration.require_verification' => ['Registration', 'Require email verification', 'bool', true, 'New accounts must verify their email.', false],
        'registration.min_password_length' => ['Registration', 'Minimum password length', 'int', 8, 'Enforced on register and password reset.', true],

        'security.session_days' => ['Security', 'SSO session length (days)', 'int', 7, 'How long a single sign-on session stays valid across all apps.', true],
        'security.login_throttle' => ['Security', 'Max failed logins / minute', 'int', 6, 'Rate-limit sign-in attempts per account.', false],
        'security.password_reset_ttl' => ['Security', 'Password reset link TTL (min)', 'int', 60, 'How long a reset link stays valid.', false],

        'fraud.risk_flag_threshold' => ['Fraud', 'Risk flag threshold', 'int', 60, 'Sign-ins scoring at or above this are flagged for review.', true],
        'fraud.block_high_risk' => ['Fraud', 'Block very high-risk sign-ins', 'bool', false, 'Reject sign-ins scoring 90+ instead of only flagging.', false],
        'fraud.geoip_url' => ['Fraud', 'Geo-IP lookup URL', 'string', '', 'e.g. http://ip-api.com/json/{ip} — blank disables geo lookups.', true],
        'fraud.block_score' => ['Fraud', 'Block score', 'int', 90, 'Sign-ins scoring at or above this are blocked (only when blocking is on).', true],
        'fraud.shared_ip_threshold' => ['Fraud', 'Shared-IP accounts threshold', 'int', 3, 'Flag when this many other accounts share the sign-in IP (account farming).', true],
        'fraud.disposable_domains' => ['Fraud', 'Extra disposable email domains', 'text', '', 'One domain per line, added to the built-in throwaway-inbox list.', true],

        'kyc.required_tier' => ['KYC', 'Tier required for payouts', 'int', 1, 'Minimum verified tier before a user can withdraw or receive payouts.', false],
        'kyc.id_types' => ['KYC', 'Accepted ID types', 'string', 'bvn,nin,passport,drivers_license,voters_card', 'Comma-separated. BVN/NIN are the Nigerian national IDs.', true],
        'kyc.auto_approve_tier1' => ['KYC', 'Auto-approve tier 1', 'bool', false, 'Approve BVN/NIN submissions automatically instead of manual review.', true],

        'sso.allowed_return_domains' => ['Single sign-on', 'Allowed return domains', 'text', "localhost\n127.0.0.1\nspurs.com.ng", 'One host per line. Apps may only redirect back to these.', false],

        'branding.platform_name' => ['Branding', 'Platform name', 'string', 'Spurs Cloud', 'Shown across the identity screens.', false],
        'branding.support_email' => ['Branding', 'Support email', 'string', 'support@spurs.com.ng', 'Where users are told to reach out.', false],
    ];

    /** Typed value for a key (DB override, else coded default). */
    public static function get(string $key): mixed
    {
        $all = self::allRaw();
        [, , $type, $default] = self::SCHEMA[$key] ?? [null, null, 'string', null];

        return self::cast($all[$key] ?? $default, $type);
    }

    /** All settings as a typed key => value map. */
    public static function all(): array
    {
        $raw = self::allRaw();
        $out = [];
        foreach (self::SCHEMA as $key => [, , $type, $default]) {
            $out[$key] = self::cast($raw[$key] ?? $default, $type);
        }

        return $out;
    }

    /** The catalogue for the admin UI: schema + current values, grouped. */
    public static function catalog(): array
    {
        $values = self::all();
        $groups = [];
        foreach (self::SCHEMA as $key => [$group, $label, $type, $default, $help, $effect]) {
            $groups[$group][] = [
                'key' => $key, 'label' => $label, 'type' => $type,
                'value' => $values[$key], 'default' => self::cast($default, $type),
                'help' => $help, 'effect' => $effect,
            ];
        }

        return $groups;
    }

    /** Persist a batch of settings (only known keys), then bust the cache. */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! isset(self::SCHEMA[$key])) {
                continue;
            }
            $type = self::SCHEMA[$key][2];
            $stored = $type === 'bool' ? ($value ? '1' : '0') : (string) $value;
            Setting::updateOrCreate(['key' => $key], ['value' => $stored]);
        }
        Cache::forget('settings.raw');
    }

    private static function allRaw(): array
    {
        return Cache::remember('settings.raw', now()->addMinutes(10), fn () => Setting::pluck('value', 'key')->all());
    }

    private static function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return $type === 'bool' ? false : ($type === 'int' ? 0 : '');
        }

        return match ($type) {
            'bool' => (bool) (is_string($value) ? in_array($value, ['1', 'true', 'on'], true) : $value),
            'int' => (int) $value,
            default => (string) $value,
        };
    }
}
