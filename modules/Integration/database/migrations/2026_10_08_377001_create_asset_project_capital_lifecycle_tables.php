<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_capital_project_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('project_code')->index();
            $table->decimal('capitalized_cost_usd', 15, 2);
            $table->boolean('cip_reconciled')->default(true); // 377.1 & 377.4
            $table->boolean('double_capitalization_prevented')->default(true); // 377.4 & 377.6 Risk
            $table->timestamps();
        });

        Schema::create('global_post_investment_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('project_code')->index();
            $table->decimal('expected_benefit_usd', 15, 2);
            $table->decimal('actual_benefit_usd', 15, 2);
            $table->boolean('benefit_realization_failed')->default(false); // 377.5 Edge case
            $table->text('learning_action_items')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_post_investment_reviews');
        Schema::dropIfExists('global_capital_project_assets');
    }
};
