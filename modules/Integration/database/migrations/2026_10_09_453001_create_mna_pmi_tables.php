<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_mna_due_diligence_deals', function (Blueprint $table) {
            $table->id();
            $table->string('deal_code')->unique();
            $table->string('target_company_name');
            $table->decimal('initial_valuation', 18, 2);
            $table->decimal('valuation_adjustment_amount', 18, 2)->default(0.00); // 453.1, 453.4
            $table->decimal('final_adjusted_valuation', 18, 2);
            $table->boolean('dd_workstreams_cleared')->default(false); // 453.1
            $table->timestamps();
        });

        Schema::create('gov_pmi_synergy_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('synergy_code')->unique();
            $table->string('deal_code');
            $table->string('synergy_type'); // cost_synergy, revenue_synergy (453.2)
            $table->decimal('target_synergy_amount', 18, 2);
            $table->decimal('realized_synergy_amount', 18, 2)->default(0.00); // 453.3, 453.4 measured not assumed
            $table->boolean('pmo_milestone_gated')->default(true); // 453.3, 453.6
            $table->boolean('underperformance_evaluated')->default(false); // 453.5 edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_pmi_synergy_trackers');
        Schema::dropIfExists('gov_mna_due_diligence_deals');
    }
};
