<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autonomy_process_registry', function (Blueprint $table) {
            $table->id();
            $table->string('process_code')->unique();
            $table->string('process_name');
            $table->integer('autonomy_level')->default(2); // 1 to 4
            $table->string('risk_tier'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->string('bounded_blast_radius_doc')->nullable(); // 268.7
            $table->decimal('audit_sampling_rate_pct', 4, 2)->default(5.00); // 268.1
            $table->integer('total_executions_count')->default(0);
            $table->integer('sampled_audits_count')->default(0);
            $table->decimal('defect_rate_pct', 5, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('autonomy_self_healing_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('anomaly_detected');
            $table->string('runbook_code');
            $table->string('automated_remediation_action'); // RESTART_CONTAINER, SCALE_REPLICAS, REGION_FAILOVER
            $table->integer('downtime_seconds')->default(0); // 268.2 zero downtime
            $table->boolean('is_incident_logged_publicly')->default(true); // 268.4 never masked
            $table->string('post_incident_report_doc');
            $table->timestamps();
        });

        Schema::create('autonomy_negotiation_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('negotiation_code')->unique();
            $table->string('vendor_name');
            $table->decimal('contract_value_usd', 15, 2);
            $table->decimal('signing_threshold_usd', 15, 2)->default(50000.00); // 268.3, 268.6
            $table->boolean('human_approval_required')->default(false);
            $table->string('human_approved_by')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, PENDING_HUMAN_APPROVAL, SIGNED_EXECUTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonomy_negotiation_contracts');
        Schema::dropIfExists('autonomy_self_healing_incidents');
        Schema::dropIfExists('autonomy_process_registry');
    }
};
