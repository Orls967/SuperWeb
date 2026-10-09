<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_safety_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('eval_code')->unique();
            $table->string('agent_identifier');
            $table->string('agent_version');
            $table->boolean('prompt_injection_blocked')->default(true); // 346.1 & 346.4
            $table->boolean('jailbreak_blocked')->default(true);
            $table->boolean('eval_suite_passed')->default(false);
            $table->boolean('release_held_fallback_prior')->default(false); // 346.5 Edge case
            $table->timestamps();
        });

        Schema::create('ai_agent_tool_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_code')->unique();
            $table->string('agent_identifier');
            $table->string('tool_name');
            $table->boolean('is_permission_expansion')->default(false); // 346.2, 346.4, 346.6 Risk
            $table->boolean('ci_change_approval_granted')->default(false);
            $table->boolean('permission_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_tool_permissions');
        Schema::dropIfExists('ai_agent_safety_evaluations');
    }
};
