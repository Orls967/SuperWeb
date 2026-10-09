<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_community_grievances', function (Blueprint $table) {
            $table->id();
            $table->string('grievance_code')->unique();
            $table->string('community_group_name');
            $table->string('issue_category'); // LAND_USE, WATER_POLLUTION, NOISE, LOCAL_MANAGEMENT
            $table->boolean('against_local_management')->default(false); // 288.6
            $table->string('assigned_escalation_channel')->default('LOCAL_OMBUDSMAN'); // INDEPENDENT_HEADQUARTERS, LOCAL_OMBUDSMAN (288.6)
            $table->string('remedy_description')->nullable();
            $table->boolean('is_remediation_completed')->default(false);
            $table->boolean('affected_party_verified_closure')->default(false); // 288.5
            $table->string('status')->default('INTAKE'); // INTAKE, REMEDIATION_IN_PROGRESS, CLOSED_VERIFIED
            $table->timestamps();
        });

        Schema::create('esg_just_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('transition_plan_code')->unique();
            $table->string('site_code')->index();
            $table->integer('affected_workforce_count');
            $table->decimal('allocated_reskilling_budget_usd', 15, 2);
            $table->decimal('income_protection_budget_usd', 15, 2); // 288.7
            $table->integer('successfully_redeployed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('esg_community_benefit_funds', function (Blueprint $table) {
            $table->id();
            $table->string('fund_code')->unique();
            $table->string('project_code')->index();
            $table->decimal('gross_revenue_usd', 15, 2);
            $table->decimal('sharing_formula_rate_pct', 5, 2)->default(2.50); // 288.4
            $table->decimal('allocated_fund_usd', 15, 2);
            $table->decimal('distributed_fund_usd', 15, 2)->default(0.00); // 288.4 & 288.5 sum matching
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_community_benefit_funds');
        Schema::dropIfExists('esg_just_transitions');
        Schema::dropIfExists('esg_community_grievances');
    }
};
