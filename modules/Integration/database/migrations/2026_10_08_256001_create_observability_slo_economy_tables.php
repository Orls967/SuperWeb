<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observability_slo_targets', function (Blueprint $table) {
            $table->id();
            $table->string('service_name'); // PAYMENT, BOOKING, CLAIM, DISPATCH, BILLING
            $table->string('sli_metric_name');
            $table->decimal('target_slo_pct', 5, 2)->default(99.90);
            $table->decimal('error_budget_total_mins', 8, 2)->default(43.20);
            $table->decimal('error_budget_consumed_mins', 8, 2)->default(0.00); // 256.8
            $table->decimal('burn_rate_ratio', 5, 2)->default(1.00);
            $table->boolean('burn_rate_alert_triggered')->default(false);
            $table->boolean('postmortem_required')->default(false); // 256.1
            $table->boolean('postmortem_completed')->default(false);
            $table->timestamps();
        });

        Schema::create('observability_business_invariants', function (Blueprint $table) {
            $table->id();
            $table->string('invariant_code')->unique();
            $table->string('invariant_type'); // LEDGER_SUM_ZERO, NO_NEGATIVE_INVENTORY, ESCROW_EXACT_MATCH, NO_OVERSELL
            $table->boolean('is_violated')->default(false);
            $table->string('incident_ticket_code')->nullable(); // 256.3
            $table->boolean('monitor_healthy')->default(true); // 256.6
            $table->boolean('meta_alert_triggered')->default(false); // 256.6
            $table->timestamps();
        });

        Schema::create('observability_traces_correlated', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id')->unique();
            $table->json('span_modules_json'); // 256.2 & 256.5 3 modules
            $table->integer('status_code')->default(200);
            $table->integer('duration_ms');
            $table->string('audit_trail_ref');
            $table->timestamps();
        });

        Schema::create('observability_partner_sla_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_code')->unique();
            $table->string('partner_id');
            $table->decimal('measured_uptime_pct', 5, 2);
            $table->decimal('sla_threshold_pct', 5, 2)->default(99.50);
            $table->boolean('is_sla_met')->default(true); // 256.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_partner_sla_reports');
        Schema::dropIfExists('observability_traces_correlated');
        Schema::dropIfExists('observability_business_invariants');
        Schema::dropIfExists('observability_slo_targets');
    }
};
