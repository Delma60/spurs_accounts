<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo + risk signals for the anti-fraud engine. Every security event now carries
 * where it came from (country/city, resolved from IP) and a risk score with the
 * signals that produced it, so the admin can monitor and triage suspicious
 * identity activity across the platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('ip');   // ISO country code
            $table->string('country_name')->nullable()->after('country');
            $table->string('city')->nullable()->after('country_name');
            $table->unsignedTinyInteger('risk')->default(0)->after('device'); // 0..100
            $table->boolean('flagged')->default(false)->after('risk');
            $table->json('signals')->nullable()->after('flagged');   // list of {code,label,weight}

            $table->index('ip');
            $table->index('flagged');
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->dropIndex(['ip']);
            $table->dropIndex(['flagged']);
            $table->dropColumn(['country', 'country_name', 'city', 'risk', 'flagged', 'signals']);
        });
    }
};
