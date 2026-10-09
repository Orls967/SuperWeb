<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_code')->unique();
            $table->string('primary_email');
            $table->string('primary_phone');
            $table->boolean('marketing_consent')->default(false); // 416.1
            $table->boolean('is_suppressed')->default(false); // 416.2 suppression lists
            $table->string('segment')->default('STANDARD');
            $table->timestamps();
        });

        Schema::create('crm_identity_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->string('merge_code')->unique();
            $table->string('source_profile_code');
            $table->string('target_profile_code');
            $table->boolean('manual_review_approved')->default(true); // 416.3, 416.5
            $table->boolean('is_reversed')->default(false); // 416.3, 416.4 reversible merge
            $table->text('reason');
            $table->string('reviewed_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_identity_merge_logs');
        Schema::dropIfExists('crm_customer_profiles');
    }
};
