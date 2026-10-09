<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_access_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('role_code');
            $table->string('line_code'); // one of 30 lines
            $table->boolean('is_privileged')->default(false); // PAM (457.2)
            $table->timestamp('recertified_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // 457.4, 457.5 auto expire
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('int_privileged_access_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code')->unique();
            $table->string('user_id');
            $table->string('elevation_type'); // jit_elevation, break_glass (457.2)
            $table->timestamp('elevated_at');
            $table->timestamp('expires_at'); // 457.4
            $table->boolean('post_review_completed')->default(false); // 457.2, 457.6
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_privileged_access_sessions');
        Schema::dropIfExists('int_access_entitlements');
    }
};
