<?php

declare(strict_types=1);

namespace Modules\Mining\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('min_reclamation_provisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('plan_code')->unique();
            $table->double('target_hectares', 10, 2);
            $table->bigInteger('provision_accrued_idr')->default(0);
            $table->bigInteger('provision_released_idr')->default(0);
            $table->double('verified_growth_ndvi', 4, 3)->default(0.0);
            $table->string('status')->default('ACCRUED'); // ACCRUED, VERIFIED, RELEASED
            $table->string('accrual_ledger_tx_id')->nullable();
            $table->string('release_ledger_tx_id')->nullable();
            $table->timestamps();

            $table->index('site_id');
        });

        Schema::create('min_water_monitoring_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('sampling_point');
            $table->double('water_volume_m3', 12, 2);
            $table->double('effluent_ph', 4, 2);
            $table->double('effluent_tss_mg_l', 8, 2); // Total Suspended Solids
            $table->boolean('threshold_exceeded')->default(false);
            $table->bigInteger('environmental_penalty_minor')->default(0);
            $table->bigInteger('water_fee_minor')->default(0);
            $table->string('ledger_transaction_id')->nullable();
            $table->timestamp('sampled_at');
            $table->timestamps();

            $table->index(['site_id', 'threshold_exceeded']);
        });

        Schema::create('min_amdal_compliance_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('site_id');
            $table->string('milestone_code')->unique();
            $table->string('document_type'); // AMDAL, RKL, RPL
            $table->string('regulator_approval_number');
            $table->boolean('is_fully_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_fully_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_amdal_compliance_milestones');
        Schema::dropIfExists('min_water_monitoring_readings');
        Schema::dropIfExists('min_reclamation_provisions');
    }
};
