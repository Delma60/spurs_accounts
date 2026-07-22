<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KYC lives on the identity provider, so one verified identity is reused by
 * every Spurs service (Wallet limits, Pay payouts, Cloud). Tiered:
 *   0 unverified · 1 BVN/NIN + phone · 2 government ID + selfie · 3 proof of address
 *
 * The raw BVN/NIN is never stored — only a masked display value and a hash, so
 * we can detect the same national ID being reused across accounts (a common
 * account-farming pattern) without holding the number itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('level')->default(0);      // highest tier attained
            $table->string('status')->default('unverified');       // unverified|pending|verified|rejected

            $table->string('id_type')->nullable();                 // bvn|nin|passport|drivers_license|voters_card
            $table->string('id_masked')->nullable();               // e.g. "*******8901"
            $table->string('id_hash')->nullable()->index();        // sha256(type:number) — duplicate detection

            $table->string('full_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->default('NG');

            $table->string('document_ref')->nullable();            // stored doc handle (ID upload)
            $table->string('selfie_ref')->nullable();

            $table->string('provider')->nullable();                // manual | verification provider
            $table->string('provider_ref')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('reviewed_by')->nullable();             // admin email

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_profiles');
    }
};
