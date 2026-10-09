<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_program_line_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('scorecard_code')->unique();
            $table->string('line_code');
            $table->decimal('weighted_maturity_score', 4, 1); // 0.0 - 100.0 (337.1 & 337.4)
            $table->integer('annual_ethics_incident_count')->default(0);
            $table->boolean('flagged_for_consistency_investigation')->default(false); // 337.5 Edge case
            $table->string('maturity_tier'); // MATURE, DEVELOPING, INCONSISTENT_ANOMALY
            $table->timestamps();
        });

        Schema::create('third_party_vendor_ethics_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('vendor_id');
            $table->string('incident_type');
            $table->boolean('vendor_speak_up_channel_verified')->default(true); // 337.2 & 337.4
            $table->boolean('has_admitted_or_substantiated_violation')->default(false);
            $table->boolean('contract_termination_exercised')->default(false); // 337.2 Termination right
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_vendor_ethics_cases');
        Schema::dropIfExists('compliance_program_line_scorecards');
    }
};
