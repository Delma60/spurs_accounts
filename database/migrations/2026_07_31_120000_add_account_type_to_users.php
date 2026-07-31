<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account type lets a Spurs identity say what it's for: a personal user, a
     * business/company, an individual merchant/seller, or a developer building on
     * the platform. Apps use it to tailor the experience (e.g. Suite is for
     * business/merchant accounts). Defaults to personal so existing rows are valid.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->default('personal')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
