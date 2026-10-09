<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_business_kpi_observables', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_code')->unique();
            $table->string('kpi_type'); // revenue_rate, order_rate, claim_rate, booking_rate, payment_success (475.1)
            $table->decimal('metric_value', 18, 4);
            $table->string('ledger_lineage_ref'); // 475.1, 475.4 lineage to ledger
            $table->timestamp('last_refreshed_at');
            $table->boolean('is_stale')->default(false); // 475.3, 475.5 edge case
            $table->timestamps();
        });

        Schema::create('int_business_kpi_anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('anomaly_code')->unique();
            $table->string('kpi_code');
            $table->decimal('drop_percentage', 5, 2);
            $table->string('triage_category')->default('unassigned'); // technical, business_market (475.2)
            $table->string('assigned_owner')->nullable();
            $table->string('status')->default('triaging'); // triaging, resolved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_business_kpi_anomalies');
        Schema::dropIfExists('int_business_kpi_observables');
    }
};
