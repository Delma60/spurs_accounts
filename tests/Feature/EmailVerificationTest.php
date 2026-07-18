<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Notification::assertSentTo(User::whereEmail('ada@spurs.com.ng')->first(), VerifyEmail::class);
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
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
