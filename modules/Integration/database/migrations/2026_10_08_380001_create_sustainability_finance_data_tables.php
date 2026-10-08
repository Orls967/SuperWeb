<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_sustainability_evidence_claims', function (Blueprint $table) {
            $table->id();
            $table->string('unique_evidence_id')->unique(); // 380.2, 380.4, 380.5 Edge case
            $table->string('claim_type'); // CARBON_CREDIT, REC, ESG_DISCLOSURE
            $table->decimal('metric_value', 12, 4);
            $table->boolean('double_counting_prevented')->default(true);
            $table->timestamps();
        });

        Schema::create('global_sustainability_transition_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->decimal('allocated_capex_usd', 15, 2);
            $table->decimal('emissions_abated_mt', 12, 4);
            $table->boolean('project_asset_ledger_linked')->default(true); // 380.3 & 380.6 Risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_sustainability_transition_plans');
        Schema::dropIfExists('global_sustainability_evidence_claims');
    }
};
