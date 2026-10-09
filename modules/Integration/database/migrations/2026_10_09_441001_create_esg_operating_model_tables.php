<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_metric_ownership_matrices', function (Blueprint $table) {
            $table->id();
            $table->string('metric_code')->unique();
            $table->string('topic'); // climate, labor, governance, diversity (441.1)
            $table->string('data_owner_name');
            $table->string('metric_steward_name'); // 441.1, 441.4, 441.6
            $table->string('assurance_provider_name');
            $table->boolean('is_orphaned')->default(false); // 441.6 risk
            $table->timestamps();
        });

        Schema::create('esg_leadership_incentive_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('scorecard_code')->unique();
            $table->string('executive_id');
            $table->string('metric_code');
            $table->decimal('target_performance', 10, 2);
            $table->decimal('actual_performance', 10, 2);
            $table->boolean('metric_verified_by_assurance')->default(false); // 441.3, 441.4
            $table->boolean('anti_gaming_guardrail_cleared')->default(true); // 441.3, 441.5
            $table->decimal('incentive_bonus_payout', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_leadership_incentive_scorecards');
        Schema::dropIfExists('esg_metric_ownership_matrices');
    }
};
