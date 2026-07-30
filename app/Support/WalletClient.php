<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Reads a user's Spurs Wallet from the wallet service's private API so the
 * account dashboard can show balances, transactions and subscriptions in one
 * place — the way Google surfaces "Payments & subscriptions".
 *
 * Fails soft: if wallet is unreachable the pages render empty rather than error.
 */
class WalletClient
{
    private static function base(): string
    {
        return rtrim((string) (config('spurs.wallet_url') ?: 'http://127.0.0.1:3200'), '/');
    }

    private static function get(string $path, array $query = []): ?array
    {
        $secret = (string) config('spurs.internal_secret');
        if ($secret === '') {
            return null;
        }

        try {
            $res = Http::withHeaders(['x-internal-secret' => $secret])
                ->timeout(4)
                ->get(self::base().$path, $query);

            return $res->successful() ? $res->json() : null;
        } catch (\Throwable) {
            return null; // wallet down — caller renders an empty state
        }
    }

    private static function post(string $path, array $body): ?array
    {
        $secret = (string) config('spurs.internal_secret');
        if ($secret === '') {
            return null;
        }

        try {
            $res = Http::withHeaders(['x-internal-secret' => $secret])
                ->timeout(6)
                ->post(self::base().$path, $body);

            return $res->successful() ? $res->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Credit a user's wallet with a platform bonus. Idempotent on `reference`:
     * the wallet pays a given reference exactly once, so a retried call is safe.
     * `amount` is in the asset's minor units (kobo for NGN). Returns the wallet's
     * response on success, or null if it was unreachable/rejected.
     *
     * @param array{source?: string, description?: string} $opts
     */
    public static function credit(string $userId, string $asset, int $amount, string $reference, array $opts = []): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        return self::post('/api/private/wallet/credit', [
            'user' => $userId,
            'asset' => $asset,
            'amount' => $amount,
            'reference' => $reference,
            'source' => $opts['source'] ?? 'referral_bonus',
            'description' => $opts['description'] ?? 'Bonus',
        ]);
    }

    /** Balances across every asset the user holds. */
    public static function balances(string $userId): array
    {
        return self::get('/api/private/wallet', ['user' => $userId])['balances'] ?? [];
    }

    /** Recent ledger entries (credits and debits). */
    public static function transactions(string $userId, int $limit = 25): array
    {
        return self::get('/api/private/wallet/transactions', ['user' => $userId, 'limit' => $limit])['items'] ?? [];
    }
}
