<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Confirm your email address", rendered from the platform's shared template so
 * it matches every other Spurs email.
 */
class VerifyEmailNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['spurs'];
    }

    /** @return array<string, mixed> */
    public function toSpurs(object $notifiable): array
    {
        return [
            'template' => 'auth.verify_email',
            'to' => $notifiable->getEmailForVerification(),
            'context' => [
                'name' => $notifiable->name,
                'verifyUrl' => $this->verificationUrl($notifiable),
            ],
        ];
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
