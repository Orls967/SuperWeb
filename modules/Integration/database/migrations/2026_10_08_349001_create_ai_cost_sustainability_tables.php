<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_cost_sustainability_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('invocation_code')->unique();
            $table->string('domain_name'); // MINING, FINANCE, ESG
            $table->string('agent_identifier');
            $table->decimal('invocation_cost_usd', 10, 4);
            $table->integer('consecutive_invocation_count')->default(1);
            $table->boolean('circuit_breaker_tripped')->default(false); // 349.5 Edge case
            $table->timestamps();
        });

        Schema::create('ai_model_tiering_runtime_enforcements', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->string('decision_stakes'); // HIGH_STAKES, LOW_RISK
            $table->string('assigned_model_tier'); // REVIEWED_PREMIUM, EFFICIENT_STANDARD
            $table->boolean('tier_policy_compliant')->default(true); // 349.3 & 349.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_tiering_runtime_enforcements');
        Schema::dropIfExists('ai_cost_sustainability_trackers');
    }
};
