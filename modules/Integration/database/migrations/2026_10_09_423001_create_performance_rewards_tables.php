<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_performance_goals', function (Blueprint $table) {
            $table->id();
            $table->string('goal_code')->unique();
            $table->string('employee_id');
            $table->string('parent_strategy_code'); // 423.1 strategy -> BU -> individual cascade
            $table->string('goal_title');
            $table->decimal('target_value', 15, 2);
            $table->boolean('mid_period_change_approved')->default(true); // 423.5 edge case
            $table->timestamps();
        });

        Schema::create('hcm_talent_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('rating_code')->unique();
            $table->string('employee_id');
            $table->string('performance_rating'); // 1 to 5
            $table->string('potential_rating'); // LOW, MED, HIGH (9-box 423.3)
            $table->boolean('calibration_bias_cleared')->default(true); // 423.2
            $table->boolean('rating_locked')->default(false); // 423.4 locked before reward payout
            $table->decimal('reward_bonus_amount', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_talent_ratings');
        Schema::dropIfExists('hcm_performance_goals');
    }
};
