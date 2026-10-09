<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_service_agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code')->unique();
            $table->string('customer_id');
            $table->boolean('ai_disclosure_provided')->default(true); // 353.3 & 353.6
            $table->boolean('resolved_autonomously')->default(false);
            $table->boolean('escalated_to_human')->default(false); // 353.1 & 353.5 Edge case
            $table->text('handoff_context_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('agentic_commerce_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->string('refund_code')->unique();
            $table->string('customer_id');
            $table->decimal('refund_amount_usd', 10, 2);
            $table->decimal('autonomous_limit_usd', 10, 2)->default(100.00); // 353.1 & 353.4
            $table->boolean('autonomous_approved')->default(false);
            $table->boolean('requires_manager_review')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agentic_commerce_refund_requests');
        Schema::dropIfExists('customer_service_agent_sessions');
    }
};
