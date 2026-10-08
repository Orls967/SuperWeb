<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_code')->unique();
            $table->string('business_line')->index();
            $table->string('pillar'); // ENVIRONMENT, SOCIAL, GOVERNANCE
            $table->string('metric_name')->index();
            $table->string('unit_of_measure');
            $table->decimal('reported_value', 15, 4)->nullable();
            $table->boolean('is_data_gap')->default(false); // 228.5 edge case flag
            $table->string('gap_reason')->nullable();
            $table->string('provenance_source'); // SENSOR, IOT_GATEWAY, LEDGER, SURVEY, MANUAL
            $table->decimal('quality_score', 3, 2); // 0.00 to 1.00
            $table->boolean('public_disclosure_eligible')->default(false);
            $table->string('reporting_period')->index(); // e.g. 2026-Q3
            $table->timestamps();
        });

        Schema::create('esg_materiality_topics', function (Blueprint $table) {
            $table->id();
            $table->string('topic_code')->unique();
            $table->string('business_line')->index();
            $table->string('topic_name');
            $table->decimal('impact_materiality_score', 3, 2);
            $table->decimal('financial_materiality_score', 3, 2);
            $table->boolean('is_material')->default(false);
            $table->boolean('in_reporting_scope')->default(false);
            $table->boolean('committee_reviewed')->default(false);
            $table->timestamps();
        });

        Schema::create('esg_disclosure_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('standard_code'); // GRI, ISSB
            $table->string('disclosure_req_code')->index();
            $table->string('metric_code')->index();
            $table->string('compliance_status'); // COMPLIANT, GAP_IDENTIFIED, NOT_APPLICABLE
            $table->string('evidence_attachment_uri')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_disclosure_mappings');
        Schema::dropIfExists('esg_materiality_topics');
        Schema::dropIfExists('esg_metrics');
    }
};
