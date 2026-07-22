<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide RBAC. Roles and permissions are defined here, on the identity
 * provider, so they are authoritative for the whole of Spurs Cloud — the roles a
 * user holds ride the SSO session and OIDC claims out to every first-party app
 * (pay, wallet, cloud, survey) and the admin control plane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // slug, e.g. "superadmin"
            $table->string('label');               // human label, e.g. "Super Admin"
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);  // system roles can't be deleted
            $table->boolean('is_default')->default(false); // auto-assigned to every new account
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();        // e.g. "pay.refund"
            $table->string('service')->index();     // e.g. "pay"
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
