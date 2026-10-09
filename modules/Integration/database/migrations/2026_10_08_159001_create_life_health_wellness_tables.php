<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 159.1: Term life & beneficiaries
        Schema::create('ins_life_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number')->unique();
            $table->unsignedBigInteger('insured_id');
            $table->string('beneficiary_name');
            $table->string('beneficiary_relation');
            $table->decimal('death_benefit_amount', 18, 2);
            $table->decimal('monthly_premium', 18, 2);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        // 159.2: Hospital cashless authorization & Guarantee Letters (GL)
        Schema::create('ins_hospital_cashless_gls', function (Blueprint $table) {
            $table->id();
            $table->string('gl_number')->unique();
            $table->string('policy_number');
            $table->string('hospital_code');
            $table->decimal('policy_annual_limit', 18, 2);
            $table->decimal('authorized_amount', 18, 2);
            $table->string('status')->default('APPROVED'); // APPROVED, EXCEEDS_LIMIT, SETTLED
            $table->timestamps();
        });

        // 159.3: Wellness activity rewards & anti-gaming
        Schema::create('ins_wellness_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('activity_date');
            $table->integer('step_count');
            $table->integer('reward_points');
            $table->boolean('is_flagged_gaming')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'activity_date']);
        });

        // 159.4: Unit Link investment NAV portfolios
        Schema::create('ins_unit_link_funds', function (Blueprint $table) {
            $table->id();
            $table->string('fund_code')->unique();
            $table->string('fund_name');
            $table->decimal('nav_per_unit', 18, 4); // Daily Net Asset Value
            $table->decimal('total_units_outstanding', 18, 4);
            $table->decimal('total_assets_under_management', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_unit_link_funds');
        Schema::dropIfExists('ins_wellness_activities');
        Schema::dropIfExists('ins_hospital_cashless_gls');
        Schema::dropIfExists('ins_life_policies');
    }
};
