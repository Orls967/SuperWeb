<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_cmdb_service_registries', function (Blueprint $table) {
            $table->id();
            $table->string('service_ci_code')->unique();
            $table->string('service_name');
            $table->string('approved_baseline_hash');
            $table->string('active_configuration_hash');
            $table->boolean('drift_detected')->default(false);
            $table->boolean('unauthorized_change')->default(false); // 362.3 & 362.5 Edge case
            $table->boolean('drift_alert_sent')->default(false);
            $table->boolean('automatic_remediation_rolled_back')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_cmdb_incident_correlations', function (Blueprint $table) {
            $table->id();
            $table->string('incident_record_code')->unique();
            $table->string('service_ci_code')->index();
            $table->string('technical_incident_id');
            $table->string('customer_issue_id');
            $table->string('correlation_id'); // 362.2 & 362.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_cmdb_incident_correlations');
        Schema::dropIfExists('platform_cmdb_service_registries');
    }
};
