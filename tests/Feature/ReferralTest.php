<?php

namespace Tests\Feature;

use App\Models\ReferralReward;
use App\Models\User;
use App\Support\Referral;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    private function enableProgram(int $bonusNaira = 500): void
    {
        Settings::save(['referral.enabled' => true, 'referral.bonus_amount' => $bonusNaira]);
        config(['spurs.wallet_url' => 'http://wallet.test', 'spurs.internal_secret' => 'test-secret']);
    }

    /** Fake a successful wallet credit (must return a JSON body, like the real API). */
    private function fakeWallet(): void
    {
        Http::fake(['*' => Http::response(['reference' => 'ok', 'asset' => 'NGN', 'amount' => 50000, 'balanceAfter' => 50000], 200)]);
    }

    public function test_referrer_is_credited_once_and_payout_is_idempotent(): void
    {
        $this->enableProgram(500);
        $this->fakeWallet();

        $referrer = User::factory()->create();
        $code = Referral::ensureCode($referrer);
        $referee = User::factory()->create();

        // Run the signup hook twice — a retry must not pay twice.
        Referral::onSignup($referee, $code);
        Referral::onSignup($referee, $code);

        // Exactly one reward row for this referee, marked paid for the full amount.
        $rewards = ReferralReward::where('referee_id', $referee->id)->get();
        $this->assertCount(1, $rewards);
        $this->assertSame('paid', $rewards->first()->status);
        $this->assertSame(50000, (int) $rewards->first()->amount_minor); // ₦500 -> kobo

        // The referee is linked to the referrer.
        $this->assertSame($referrer->id, $referee->fresh()->referred_by);

        // The wallet was credited exactly once, on a stable idempotency reference.
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/private/wallet/credit')
            && $req['reference'] === "referral:{$referee->id}"
            && $req['amount'] === 50000
            && $req['source'] === 'referral_bonus');
    }

    public function test_no_reward_when_program_disabled(): void
    {
        Settings::save(['referral.enabled' => false, 'referral.bonus_amount' => 500]);
        config(['spurs.wallet_url' => 'http://wallet.test', 'spurs.internal_secret' => 'test-secret']);
        $this->fakeWallet();

        $referrer = User::factory()->create();
        $code = Referral::ensureCode($referrer);
        $referee = User::factory()->create();

        Referral::onSignup($referee, $code);

        $this->assertDatabaseCount('referral_rewards', 0);
        $this->assertNull($referee->fresh()->referred_by);
        Http::assertNothingSent();
    }

    public function test_self_referral_is_ignored(): void
    {
        $this->enableProgram(500);
        $this->fakeWallet();

        $user = User::factory()->create();
        $code = Referral::ensureCode($user);

        Referral::onSignup($user, $code); // using your own code

        $this->assertDatabaseCount('referral_rewards', 0);
        Http::assertNothingSent();
    }

    public function test_failed_payout_is_reconciled_on_retry(): void
    {
        $this->enableProgram(500);

        // First attempt: wallet returns an empty body, so the credit reads as failed.
        Http::fake(['*' => Http::response('', 200)]);

        $referrer = User::factory()->create();
        $code = Referral::ensureCode($referrer);
        $referee = User::factory()->create();

        Referral::onSignup($referee, $code);

        $reward = ReferralReward::where('referee_id', $referee->id)->firstOrFail();
        $this->assertSame('failed', $reward->status);

        // Wallet recovers — now it returns a proper body.
        $this->fakeWallet();

        $result = Referral::retryFailed();

        $this->assertSame(1, $result['attempted']);
        $this->assertSame(1, $result['paid']);
        $this->assertSame('paid', $reward->fresh()->status);
        $this->assertNotNull($reward->fresh()->paid_at);

        // Retrying again is a no-op — already paid, nothing re-attempted.
        Http::fake(); // any further credit call would be caught here
        $again = Referral::retryFailed();
        $this->assertSame(0, $again['attempted']);
    }

    public function test_retry_command_runs(): void
    {
        $this->enableProgram(500);
        $this->fakeWallet();

        $this->artisan('referrals:retry-payouts')
            ->assertExitCode(0);
    }

    public function test_registering_with_a_ref_code_links_and_rewards(): void
    {
        $this->enableProgram(500);
        $this->fakeWallet();

        $referrer = User::factory()->create();
        $code = Referral::ensureCode($referrer);

        $this->post('/register', [
            'name' => 'Referred User',
            'email' => 'referred@spurs.com.ng',
            'phone' => '+2348012345678',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'ref' => $code,
        ])->assertRedirect();

        $referee = User::where('email', 'referred@spurs.com.ng')->firstOrFail();
        $this->assertSame($referrer->id, $referee->referred_by);
        $this->assertDatabaseHas('referral_rewards', [
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'status' => 'paid',
        ]);
    }
}
