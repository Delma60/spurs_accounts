<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-account trust score (0–100). The inverse-in-spirit of per-event risk:
 * a slow-moving reputation built from verification, tenure, and consistent,
 * low-risk activity. Surfaced to the admin now; a natural input to future
 * credit/loan eligibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('trust_score')->nullable()->after('email_verified_at');
            $table->timestamp('trust_updated_at')->nullable()->after('trust_score');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trust_score', 'trust_updated_at']);
        });
    }
};
