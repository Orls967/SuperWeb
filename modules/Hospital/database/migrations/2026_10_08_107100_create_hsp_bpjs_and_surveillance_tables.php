<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 107.1 JKN/BPJS Claim Batches with Gapless Sequencing
        Schema::create('hsp_bpjs_claim_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 32)->unique(); // Gapless batch sequence
            $table->string('claim_month', 7); // YYYY-MM
            $table->integer('total_episodes')->default(0);
            $table->bigInteger('total_claimed_idr')->default(0);
            $table->bigInteger('approved_amount_idr')->default(0);
            $table->bigInteger('denied_amount_idr')->default(0);
            $table->string('status', 32)->default('SUBMITTED'); // SUBMITTED, IN_VERIFICATION, APPROVED, PARTIALLY_DENIED, REJECTED
            $table->timestamps();
        });

        // 107.1 JKN Claim Batch Items
        Schema::create('hsp_bpjs_claim_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('billing_episode_id');
            $table->string('drg_code', 32);
            $table->bigInteger('drg_tariff_idr');
            $table->string('verification_status', 32)->default('SUBMITTED'); // SUBMITTED, APPROVED, DENIED
            $table->string('denial_reason', 128)->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('hsp_bpjs_claim_batches')->cascadeOnDelete();
            $table->foreign('billing_episode_id')->references('id')->on('hsp_billing_episodes')->cascadeOnDelete();
        });

        // 107.3 Public Health Surveillance / Outbreak Clusters
        Schema::create('hsp_epidemic_surveillances', function (Blueprint $table) {
            $table->id();
            $table->string('cluster_code', 32)->unique();
            $table->string('region_code', 32);
            $table->string('disease_syndrome', 64); // DENGUE, CHOLERA, RESPIRATORY_COVID, MEASLES
            $table->integer('case_count');
            $table->integer('alert_threshold');
            $table->boolean('outbreak_alarm_triggered')->default(false);
            $table->string('status', 32)->default('MONITORING'); // MONITORING, OUTBREAK_ALERT, CONTAINED
            $table->timestamps();
        });

        // 107.4 Health Command Center Bed and Staffing Forecasts
        Schema::create('hsp_command_center_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_code', 32)->unique();
            $table->date('target_date');
            $table->integer('predicted_admissions');
            $table->integer('recommended_nurse_shifts');
            $table->integer('recommended_active_beds');
            $table->integer('blood_units_needed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_command_center_forecasts');
        Schema::dropIfExists('hsp_epidemic_surveillances');
        Schema::dropIfExists('hsp_bpjs_claim_items');
        Schema::dropIfExists('hsp_bpjs_claim_batches');
    }
};
