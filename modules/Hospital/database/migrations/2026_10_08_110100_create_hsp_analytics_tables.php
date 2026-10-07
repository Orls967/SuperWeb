<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 110.1 & 110.5 Hospital Performance Scorecards
        Schema::create('hsp_group_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('scorecard_code', 32)->unique();
            $table->string('hospital_code', 32);
            $table->string('hospital_name', 128);
            $table->integer('period_year');
            $table->integer('period_month');
            $table->integer('total_admissions');
            $table->decimal('mortality_rate_percent', 5, 2)->default(0);
            $table->decimal('readmission_rate_percent', 5, 2)->default(0);
            $table->decimal('patient_satisfaction_score', 4, 2)->default(4.5);
            $table->bigInteger('total_revenue_idr')->default(0);
            $table->bigInteger('bpjs_receivable_idr')->default(0);
            $table->bigInteger('insurance_receivable_idr')->default(0);
            $table->decimal('overall_quality_score', 5, 2)->default(85.0);
            $table->timestamps();
        });

        // 110.3 Equipment ROI Analytics
        Schema::create('hsp_equipment_rois', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_code', 32)->unique();
            $table->string('modality', 32); // CT_SCAN, MRI_3T, PET_CT
            $table->bigInteger('capital_expenditure_idr');
            $table->bigInteger('cumulative_revenue_idr')->default(0);
            $table->integer('total_procedures_done')->default(0);
            $table->decimal('roi_percentage', 6, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_equipment_rois');
        Schema::dropIfExists('hsp_group_scorecards');
    }
};
