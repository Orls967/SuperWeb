<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 196.1: Agent runtime with role tool whitelisting and step budgets
        Schema::create('ai_agent_runtimes', function (Blueprint $table) {
            $table->id();
            $table->string('agent_code')->unique(); // e.g. AGT-PROCURE-01
            $table->string('agent_role'); // PROCUREMENT, CLAIMS, OPS
            $table->json('tool_whitelist'); // allowed tools array
            $table->integer('max_step_budget')->default(10);
            $table->boolean('is_killed')->default(false); // Kill-switch
            $table->timestamps();
        });

        // 196.2: Human-in-the-loop review queues for high-risk actions
        Schema::create('ai_hitl_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code')->unique();
            $table->string('agent_code');
            $table->string('action_name'); // DISBURSE_MONEY, APPROVE_CLAIM, SIGN_CONTRACT
            $table->decimal('risk_amount_idr', 18, 2)->default(0.00);
            $table->string('required_reviewer_role');
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->text('reviewer_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_hitl_reviews');
        Schema::dropIfExists('ai_agent_runtimes');
    }
};
