<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_environment_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->string('environment'); // DEV, TEST, STAGING, PROD
            $table->string('data_type'); // STRING, INTEGER, BOOLEAN, SECRET_REF
            $table->string('config_value');
            $table->boolean('is_secret_reference_only')->default(false); // 296.1 & 296.4
            $table->boolean('is_valid_schema')->default(true); // 296.1 Invalid config prevents startup
            $table->timestamps();
        });

        Schema::create('platform_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flag_key')->unique();
            $table->string('owner_lead_id');
            $table->string('rollout_scope'); // GLOBAL, REGION_EAST, TENANT_ENTERPRISE
            $table->boolean('is_enabled')->default(false);
            $table->date('adoption_expiry_deadline'); // 296.2 & 296.6
            $table->boolean('is_stale_purged')->default(false); // 296.6
            $table->timestamps();
        });

        Schema::create('platform_environment_drifts', function (Blueprint $table) {
            $table->id();
            $table->string('drift_detection_code')->unique();
            $table->string('target_environment'); // STAGING, PROD
            $table->string('drift_component'); // SCHEMA, CONFIG, QUEUES
            $table->boolean('drift_detected')->default(false); // 296.3 & 296.7
            $table->boolean('staging_promotion_gate_blocked')->default(false); // 296.3
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_environment_drifts');
        Schema::dropIfExists('platform_feature_flags');
        Schema::dropIfExists('platform_environment_configurations');
    }
};
