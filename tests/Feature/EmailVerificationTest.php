<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_screen_renders_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/email/verify')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/VerifyEmail'));
    }

    public function test_verified_user_is_redirected_away_from_notice(): void
    {
        $user = User::factory()->create(); // factory verifies by default

        $this->actingAs($user)->get('/email/verify')->assertRedirect('/me');
    }

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@spurs.com.ng',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        Notification::assertSentTo(User::whereEmail('ada@spurs.com.ng')->first(), VerifyEmailNotification::class);
    }

    public function test_email_can_be_verified_via_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect('/me?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_a_new_link(): void
    {
        config(['services.spurs_mail.url' => 'http://mail.test', 'services.spurs_mail.secret' => 'secret']);
        Http::fake([
            'http://mail.test/api/private/mail/send' => Http::response(['ok' => true], 200),
        ]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-sent');
    }

    public function test_resend_reports_failure_when_mail_service_rejects(): void
    {
        config(['services.spurs_mail.url' => 'http://mail.test', 'services.spurs_mail.secret' => 'secret']);
        Http::fake([
            'http://mail.test/api/private/mail/send' => Http::response(['ok' => false, 'error' => 'bad'], 500),
        ]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-failed');
    }
}
