<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referrals: every user gets a shareable code; a new account can name the
     * code that invited it (`referred_by`). Reward payouts are tracked in their
     * own ledger — one row per invited user (unique), so a referrer is paid for
     * a given referee exactly once even if the payout is retried.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('currency');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor'); // reward in kobo
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 16)->default('pending'); // pending | paid | failed
            $table->string('reference')->unique();           // idempotency key for the wallet credit
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn('referral_code');
        });
    }
};
