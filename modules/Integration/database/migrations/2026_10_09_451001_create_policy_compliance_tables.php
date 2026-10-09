<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_policy_compliance_findings', function (Blueprint $table) {
            $table->id();
            $table->string('finding_code')->unique();
            $table->string('policy_area'); // e.g. aml_cdd, procurement, data_privacy
            $table->boolean('is_statistically_valid_sample')->default(true); // 451.4, 451.6
            $table->boolean('is_repeat_finding')->default(false); // 451.3, 451.5
            $table->text('systemic_root_cause_analysis')->nullable(); // 451.3, 451.5
            $table->text('redesigned_control_details')->nullable();
            $table->boolean('independent_verifier_closed')->default(false); // 451.3, 451.4
            $table->string('status')->default('open'); // open, remediated, verified_closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_policy_compliance_findings');
    }
};
