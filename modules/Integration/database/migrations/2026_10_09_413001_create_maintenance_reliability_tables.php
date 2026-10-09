<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_maintenance_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->unique();
            $table->string('asset_class');
            $table->string('criticality_rank'); // A_CRITICAL, B_MEDIUM, C_LOW
            $table->string('strategy'); // predictive, preventive, condition_based, run_to_failure
            $table->decimal('pm_compliance_rate', 5, 2)->default(100.00); // % on-time
            $table->integer('backlog_aging_days')->default(0);
            $table->boolean('escalated_to_supervisor')->default(false); // 413.5 edge case
            $table->string('responsible_engineer');
            $table->timestamps();
        });

        Schema::create('ops_reliability_improvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('ops_maintenance_programs')->cascadeOnDelete();
            $table->string('improvement_code')->unique();
            $table->string('chronic_failure_cause');
            $table->text('design_or_operating_change');
            $table->boolean('effectiveness_verified')->default(true); // 413.3 & 413.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_reliability_improvements');
        Schema::dropIfExists('ops_maintenance_programs');
    }
};
