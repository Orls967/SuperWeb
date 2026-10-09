<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 86.1 Satellite NDVI scans for plasma agricultural plots
        Schema::create('agri_satellite_scans', function (Blueprint $table) {
            $table->id();
            $table->string('scan_code', 32)->unique();
            $table->string('plot_code', 32);
            $table->date('scan_date');
            $table->decimal('ndvi_score', 4, 3); // -1.0 to +1.0 (e.g. 0.725)
            $table->string('crop_health_status', 32); // HEALTHY, MILD_STRESS, SEVERE_STRESS, CRITICAL
            $table->json('polygon_geojson')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();
        });

        // 86.2 Loan installment disbursement conditional on NDVI benchmark
        Schema::create('agri_financing_installments', function (Blueprint $table) {
            $table->id();
            $table->string('installment_code', 32)->unique();
            $table->string('contract_farming_code', 64);
            $table->string('plot_code', 32);
            $table->integer('tranche_number');
            $table->bigInteger('amount_idr');
            $table->decimal('required_min_ndvi', 4, 3)->default(0.650);
            $table->decimal('evaluated_ndvi', 4, 3)->nullable();
            $table->string('status', 32)->default('PENDING_EVALUATION'); // PENDING_EVALUATION, DISBURSED, HELD_CORRECTIVE_PLAN, RESTRUCTURED
            $table->text('corrective_action_plan')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });

        // 86.4 DAO Proposals, Voter weights, and Hash-chained voting
        Schema::create('gov_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code', 32)->unique();
            $table->string('title', 128);
            $table->string('proposal_type', 32); // EXPANSION_RESTO, ACQUISITION_FACTORY, BUDGET_ALLOCATION
            $table->text('description');
            $table->json('action_payload');
            $table->bigInteger('quorum_weight_required');
            $table->bigInteger('total_yes_weight')->default(0);
            $table->bigInteger('total_no_weight')->default(0);
            $table->bigInteger('total_abstain_weight')->default(0);
            $table->string('status', 32)->default('ACTIVE'); // ACTIVE, APPROVED, REJECTED, EXECUTED
            $table->string('execution_reference_code', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('gov_voter_weights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voter_user_id')->unique();
            $table->string('voter_role', 32); // EMPLOYEE_HCM, RWA_HOLDER, FRANCHISEE_RESTO, STRATEGIC_PARTNER
            $table->bigInteger('voting_weight');
            $table->string('passport_hash', 64);
            $table->timestamps();
        });

        Schema::create('gov_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('voter_user_id');
            $table->string('vote_choice', 16); // YES, NO, ABSTAIN
            $table->bigInteger('weight_cast');
            $table->string('previous_vote_hash', 64)->nullable();
            $table->string('vote_receipt_hash', 64);
            $table->timestamps();

            $table->unique(['proposal_id', 'voter_user_id']);
            $table->foreign('proposal_id')->references('id')->on('gov_proposals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_votes');
        Schema::dropIfExists('gov_voter_weights');
        Schema::dropIfExists('gov_proposals');
        Schema::dropIfExists('agri_financing_installments');
        Schema::dropIfExists('agri_satellite_scans');
    }
};
