<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 190.1 & 190.2: Exception triage & operational alert center across 30 lines
        Schema::create('cmd_operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_code')->unique();
            $table->string('dedup_fingerprint')->unique(); // Duplicate alerts merge
            $table->string('line_code'); // L01 - L30
            $table->string('category'); // MONEY, SAFETY, CUSTOMER, COMPLIANCE
            $table->string('assigned_owner_role');
            $table->integer('sla_response_minutes')->default(30);
            $table->string('escalation_level')->default('LEVEL_1');
            $table->string('status')->default('OPEN'); // OPEN, ESCALATED, CLOSED
            $table->timestamps();
        });

        // 190.3: Daily cadence end-of-day close across lines
        Schema::create('cmd_daily_eod_closes', function (Blueprint $table) {
            $table->id();
            $table->string('close_batch_code')->unique();
            $table->date('close_date');
            $table->string('line_code');
            $table->decimal('total_revenue_idr', 18, 2);
            $table->decimal('posted_ledger_idr', 18, 2);
            $table->decimal('eod_variance_idr', 18, 2)->default(0.00); // Invariant Σ variance == 0
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmd_daily_eod_closes');
        Schema::dropIfExists('cmd_operational_alerts');
    }
};
