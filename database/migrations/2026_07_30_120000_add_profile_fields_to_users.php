<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registration now captures a phone number and derives the account's home
     * country + default currency (from the sign-up IP). Every field is nullable
     * so existing accounts stay valid and can backfill later.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->char('country', 2)->nullable()->after('phone');
            $table->char('currency', 3)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'country', 'currency']);
        });
    }
};
