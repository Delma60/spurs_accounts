<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves an IP address to a rough location for the anti-fraud engine.
 *
 * Deliberately provider-agnostic: point SPURS_GEOIP_URL at any lookup service
 * that returns JSON (default shape matches ip-api.com) and it will be used;
 * otherwise we degrade gracefully — private/loopback addresses are labelled
 * "Local network" and everything else is "Unknown". Never throws, never blocks
 * a request for long (short timeout, cached).
 */
class GeoIp
{
    /** @return array{country: ?string, country_name: ?string, city: ?string, label: string} */
    public static function lookup(?string $ip): array
    {
        if (! $ip || self::isPrivate($ip)) {
            return ['country' => null, 'country_name' => null, 'city' => null, 'label' => 'Local network'];
        }

        return Cache::remember("geoip:{$ip}", now()->addHours(12), function () use ($ip) {
            $url = Settings::get('fraud.geoip_url') ?: config('spurs.geoip_url');
            if (! $url) {
                return ['country' => null, 'country_name' => null, 'city' => null, 'label' => 'Unknown'];
            }

            try {
                $res = Http::timeout(2)->get(str_replace('{ip}', $ip, $url));
                if ($res->ok()) {
                    $d = $res->json();
                    // Map common provider field names (ip-api.com / ipinfo.io style).
                    $code = $d['countryCode'] ?? $d['country'] ?? null;
                    $name = $d['country_name'] ?? $d['country'] ?? $code;
                    $city = $d['city'] ?? null;

                    return [
                        'country' => $code ? substr((string) $code, 0, 2) : null,
                        'country_name' => $name,
                        'city' => $city,
                        'label' => trim(($city ? "{$city}, " : '').($name ?? 'Unknown'), ', ') ?: 'Unknown',
                    ];
                }
            } catch (\Throwable) {
                // fall through to Unknown
            }

            return ['country' => null, 'country_name' => null, 'city' => null, 'label' => 'Unknown'];
        });
    }

    private static function isPrivate(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
