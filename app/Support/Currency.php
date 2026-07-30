<?php

namespace App\Support;

/**
 * Maps an ISO country code to the account's default currency.
 *
 * Kept deliberately small and explicit — it only needs to cover the markets we
 * actually serve, and everything else falls back to USD. A new account's home
 * currency is derived from where they signed up ({@see GeoIp}); the user can
 * still change it later in Wallet.
 */
class Currency
{
    /** Sensible platform default when we can't tell where a user is. */
    public const DEFAULT = 'NGN';

    /** ISO-3166 alpha-2 → ISO-4217. */
    private const MAP = [
        'NG' => 'NGN', // Nigeria (home market)
        'GH' => 'GHS', // Ghana
        'KE' => 'KES', // Kenya
        'ZA' => 'ZAR', // South Africa
        'EG' => 'EGP', // Egypt
        'TZ' => 'TZS', // Tanzania
        'UG' => 'UGX', // Uganda
        'RW' => 'RWF', // Rwanda
        'US' => 'USD',
        'GB' => 'GBP',
        'CA' => 'CAD',
        'AU' => 'AUD',
        'IN' => 'INR',
        'CN' => 'CNY',
        'JP' => 'JPY',
        'BR' => 'BRL',
        // Eurozone
        'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR',
        'NL' => 'EUR', 'IE' => 'EUR', 'PT' => 'EUR', 'BE' => 'EUR',
        'AT' => 'EUR', 'FI' => 'EUR', 'GR' => 'EUR',
    ];

    /** Resolve a currency for a country code, defaulting to USD. */
    public static function forCountry(?string $country): string
    {
        if (! $country) {
            return 'USD';
        }

        return self::MAP[strtoupper($country)] ?? 'USD';
    }
}
