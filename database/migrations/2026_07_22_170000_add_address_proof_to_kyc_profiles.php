<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 3 evidence: proof of address. Stored as a private disk path (never a
 * public URL) plus the kind of document supplied, so a reviewer knows what
 * they're looking at before opening it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->string('address_proof_ref')->nullable()->after('selfie_ref');
            $table->string('address_proof_type')->nullable()->after('address_proof_ref'); // utility_bill|bank_statement|tenancy_agreement
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn(['address_proof_ref', 'address_proof_type']);
        });
    }
};
