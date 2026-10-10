<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_optimization_problem_registries', function (Blueprint $table) {
            $table->id();
            $table->string('problem_code')->unique();
            $table->string('domain_name'); // MINING, SUPPLY_CHAIN, ENERGY
            $table->string('solver_version');
            $table->string('business_owner');
            $table->boolean('governance_approval_granted')->default(false); // 348.1 & 348.4
            $table->boolean('solver_deployment_permitted')->default(false);
            $table->timestamps();
        });

        Schema::create('decision_optimization_recommendation_audits', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_code')->unique('dec_opt_rec_audits_rec_code_uniq');
            $table->string('problem_code')->index();
            $table->decimal('recommended_value', 15, 2);
            $table->decimal('safety_min_bound', 15, 2);
            $table->decimal('safety_max_bound', 15, 2);
            $table->boolean('is_extreme_recommendation')->default(false); // 348.5 Edge case
            $table->boolean('recommendation_blocked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_optimization_recommendation_audits');
        Schema::dropIfExists('decision_optimization_problem_registries');
    }
};
