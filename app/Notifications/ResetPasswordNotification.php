<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * "Reset your password", rendered from the platform's shared template.
 *
 * The token stays a public property because Laravel's password broker — and the
 * tests around it — read it straight off the notification.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['spurs'];
    }

    /** @return array<string, mixed> */
    public function toSpurs(object $notifiable): array
    {
        return [
            'template' => 'auth.reset_password',
            'to' => $notifiable->getEmailForPasswordReset(),
            'context' => [
                'name' => $notifiable->name,
                'resetUrl' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]),
                'expiresMinutes' => config('auth.passwords.users.expire', 60),
            ],
        ];
    }
}
