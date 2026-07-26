<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 is now BVN-only (plus phone); Tier 2 additionally requires a second,
 * distinct national ID (NIN, passport, driver's licence or voter's card) on
 * top of the document + selfie already collected. Stored separately from the
 * Tier 1 identifier so neither overwrites the other and duplicate-detection
 * keeps working for both tiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->string('tier2_id_type')->nullable()->after('id_hash');
            $table->string('tier2_id_masked')->nullable()->after('tier2_id_type');
            $table->string('tier2_id_hash')->nullable()->index()->after('tier2_id_masked');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn(['tier2_id_type', 'tier2_id_masked', 'tier2_id_hash']);
        });
    }
};
