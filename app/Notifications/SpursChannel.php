<?php

namespace App\Notifications;

use App\Support\SpursMailer;
use Illuminate\Notifications\Notification;

/**
 * Delivers notifications through the platform mailer instead of Laravel's own
 * mail transport.
 *
 * Keeping Laravel's notification pipeline (rather than bypassing it) means the
 * password broker still mints and carries its token, `Notification::fake()`
 * still works in tests, and queueing stays available — while the actual sending,
 * credentials and templates all still live in admin.
 *
 * A notification opts in by returning `['spurs']` from `via()` and implementing
 * `toSpurs()`.
 */
class SpursChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSpurs')) {
            return;
        }

        $message = $notification->toSpurs($notifiable);
        $to = $message['to'] ?? $notifiable->routeNotificationFor('mail', $notification);

        if (! $to) {
            return;
        }

        SpursMailer::send(
            $message['template'],
            is_array($to) ? array_key_first($to) : $to,
            $message['context'] ?? [],
            $message['idempotencyKey'] ?? null,
        );
    }
}
