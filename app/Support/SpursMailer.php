<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Platform email.
 *
 * Accounts doesn't hold SMTP credentials and doesn't style its own emails — it
 * posts a template key and a context to the admin control plane, which owns the
 * provider, the house style and the delivery log. So a Spurs verification email
 * looks like a Spurs gift card email, and support has one place to look when
 * someone says they never got it.
 *
 * Nothing here throws. A registration must not fail because an email did; a
 * failure is logged and surfaced in the admin mail log instead.
 */
class SpursMailer
{
    /**
     * Send a templated email.
     *
     * @param  array<string, mixed>  $context
     * @param  string|null  $idempotencyKey  Pass whenever the caller might retry —
     *                                       sending is idempotent on it.
     */
    public static function send(
        string $template,
        string $to,
        array $context = [],
        ?string $idempotencyKey = null,
    ): bool {
        $url = rtrim((string) config('services.spurs_mail.url'), '/');
        $secret = (string) config('services.spurs_mail.secret');

        if (! $secret) {
            Log::warning('SpursMailer: no INTERNAL_API_SECRET, email not sent', compact('template', 'to'));

            return false;
        }

        try {
            $response = Http::withHeaders(['x-internal-secret' => $secret])
                ->acceptJson()
                ->timeout(15)
                ->post($url.'/api/private/mail/send', array_filter([
                    'app' => 'accounts',
                    'template' => $template,
                    'to' => $to,
                    'context' => $context,
                    'idempotencyKey' => $idempotencyKey,
                ], fn ($v) => $v !== null));

            if ($response->successful() && ($response->json('ok') === true)) {
                return true;
            }

            Log::warning('SpursMailer: send rejected', [
                'template' => $template,
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);
        } catch (\Throwable $e) {
            // The mail service being unreachable must never take a signup down.
            Log::warning('SpursMailer: could not reach the mail service', [
                'template' => $template,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
