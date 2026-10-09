<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 202.1 & 202.2: Enterprise Risk Register across 30 lines
        Schema::create('erm_risk_registers', function (Blueprint $table) {
            $table->id();
            $table->string('risk_code')->unique();
            $table->string('domain_code');
            $table->string('risk_category'); // STRATEGIC, OPERATIONAL, FINANCIAL, COMPLIANCE, TECH
            $table->string('risk_title');
            $table->integer('likelihood_score'); // 1 to 5
            $table->integer('impact_score'); // 1 to 5
            $table->integer('inherent_risk_score'); // likelihood * impact
            $table->string('treatment_strategy'); // AVOID, MITIGATE, TRANSFER, ACCEPT
            $table->integer('residual_risk_score');
            $table->timestamps();
        });

        // 202.3 & 202.4: Key Risk Indicators (KRI) & Risk Appetite Thresholds
        Schema::create('erm_kri_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('kri_code')->unique();
            $table->string('domain_code');
            $table->string('kri_name');
            $table->decimal('max_appetite_threshold', 10, 2);
            $table->decimal('actual_kri_value', 10, 2)->default(0.00);
            $table->boolean('appetite_breached')->default(false);
            $table->string('board_escalation_status')->default('NORMAL'); // NORMAL, ESCALATED_TO_BOARD
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_kri_monitors');
        Schema::dropIfExists('erm_risk_registers');
    }
};
