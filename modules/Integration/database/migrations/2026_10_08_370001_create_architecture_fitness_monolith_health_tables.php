<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_architecture_fitness_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_code')->unique();
            $table->string('module_name');
            $table->boolean('has_cross_module_persistence_violation')->default(false); // 370.2, 370.4, 370.5 Edge case
            $table->boolean('all_tables_owned_by_domain')->default(true); // 370.3
            $table->boolean('fitness_gate_passed')->default(false);
            $table->boolean('ci_merge_permitted')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_architecture_quarterly_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('quarter_code'); // Q1-2026, Q2-2026
            $table->boolean('coupling_review_completed')->default(true); // 370.6 Risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_architecture_quarterly_reviews');
        Schema::dropIfExists('platform_architecture_fitness_evaluations');
    }
};
