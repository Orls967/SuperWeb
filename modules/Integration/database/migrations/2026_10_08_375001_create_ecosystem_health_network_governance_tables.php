<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecosystem_fairness_search_rankings', function (Blueprint $table) {
            $table->id();
            $table->string('ranking_code')->unique();
            $table->string('item_code');
            $table->boolean('is_platform_owned_item')->default(false);
            $table->boolean('self_preferencing_boost_applied')->default(false); // 375.2 & 375.4
            $table->decimal('reproducible_rank_score', 8, 4);
            $table->timestamps();
        });

        Schema::create('ecosystem_health_interventions', function (Blueprint $table) {
            $table->id();
            $table->string('intervention_code')->unique();
            $table->string('partner_code')->index();
            $table->decimal('subsidy_amount_usd', 12, 2);
            $table->decimal('subsidy_cap_usd', 12, 2)->default(5000.00); // 375.3 & 375.4
            $table->boolean('net_negative_market_distortion')->default(false); // 375.5 Edge case
            $table->boolean('intervention_terminated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecosystem_health_interventions');
        Schema::dropIfExists('ecosystem_fairness_search_rankings');
    }
};
