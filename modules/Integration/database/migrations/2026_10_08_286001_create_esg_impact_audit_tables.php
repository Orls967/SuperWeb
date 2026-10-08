<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_impact_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('verifier_agency_id')->index();
            $table->boolean('has_conflict_of_interest')->default(false); // 286.2 & 286.6
            $table->decimal('verified_carbon_abatement_tons', 15, 2)->default(0.00);
            $table->string('assurance_statement_doc')->nullable();
            $table->boolean('payout_approved')->default(false); // 286.2
            $table->timestamps();
        });

        Schema::create('esg_sustainability_linked_loans', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code')->unique();
            $table->decimal('principal_amount_usd', 15, 2);
            $table->decimal('base_margin_interest_pct', 5, 2);
            $table->decimal('kpi_target_emissions_reduction_pct', 5, 2);
            $table->decimal('actual_emissions_reduction_pct', 5, 2)->default(0.00);
            $table->decimal('adjusted_interest_rate_pct', 5, 2); // 286.3 & 286.7
            $table->boolean('kpi_threshold_met')->default(false);
            $table->timestamps();
        });

        Schema::create('esg_public_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_code')->unique();
            $table->text('claim_statement');
            $table->string('evidence_document_ref'); // 286.4 Mandatory evidence mapping
            $table->boolean('legal_compliance_approved')->default(false); // 286.4 Anti-greenwashing
            $table->date('revalidation_expiry_date');
            $table->boolean('is_expired')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_public_claims');
        Schema::dropIfExists('esg_sustainability_linked_loans');
        Schema::dropIfExists('esg_impact_verifications');
    }
};
