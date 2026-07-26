<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reversible storage of a verified BVN for internal reuse by Wallet/Pay when
 * provisioning virtual accounts. This is distinct from id_hash, which is a one-way
 * duplicate-detection fingerprint and must never be used to recover the original ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->text('id_encrypted')->nullable()->after('id_hash');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn('id_encrypted');
        });
    }
};
