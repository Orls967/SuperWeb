<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_marketplace_registered_agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_code')->unique();
            $table->string('agent_name');
            $table->string('risk_tier'); // LOW, MEDIUM, CRITICAL
            $table->boolean('is_registered_in_marketplace')->default(false); // 358.1 & 358.4
            $table->boolean('is_quarantined')->default(false); // 358.5 Edge case
            $table->string('quarantine_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_marketplace_tool_executions', function (Blueprint $table) {
            $table->id();
            $table->string('execution_code')->unique();
            $table->string('agent_code')->index();
            $table->string('tool_name');
            $table->boolean('tool_permission_granted')->default(false); // 358.2 & 358.4
            $table->boolean('secrets_exposed_to_context')->default(false); // 358.3 & 358.6 Risk
            $table->boolean('execution_permitted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_marketplace_tool_executions');
        Schema::dropIfExists('agent_marketplace_registered_agents');
    }
};
