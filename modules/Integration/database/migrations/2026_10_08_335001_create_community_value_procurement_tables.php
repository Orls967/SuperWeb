<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_supplier_development_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->unique();
            $table->string('local_supplier_id');
            $table->string('region_code');
            $table->boolean('has_objective_milestone_evidence')->default(false); // 335.1 & 335.4
            $table->boolean('tender_eligibility_unlocked')->default(false);
            $table->boolean('has_measurable_outcome')->default(true); // 335.5 Edge case
            $table->string('program_classification')->default('MEASURED_IMPACT');
            $table->timestamps();
        });

        Schema::create('community_grievance_remediations', function (Blueprint $table) {
            $table->id();
            $table->string('grievance_code')->unique();
            $table->string('community_id');
            $table->decimal('remedy_budget_usd', 15, 2);
            $table->boolean('remedy_implemented')->default(false);
            $table->boolean('confirmed_by_community_rep')->default(false); // 335.3 & 335.4
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_grievance_remediations');
        Schema::dropIfExists('local_supplier_development_programs');
    }
};
