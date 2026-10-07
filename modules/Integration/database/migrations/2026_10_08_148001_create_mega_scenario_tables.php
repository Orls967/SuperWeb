<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 148: Golden Mega-Scenario & Conglomerate Simulation Registry
        Schema::create('mega_scenario_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code')->unique();
            $table->string('scenario_type'); // CONGLOMERATE_12M, GOLDEN_MEGA_CHAIN, CRISIS_BLACKOUT, MA_ACQUISITION
            $table->string('status')->default('PENDING'); // PENDING, RUNNING, COMPLETED, FAILED
            $table->integer('total_steps')->default(0);
            $table->integer('completed_steps')->default(0);
            $table->json('execution_log')->nullable();
            $table->json('audit_discrepancies')->nullable();
            $table->decimal('total_group_pnl', 16, 2)->default(0.00);
            $table->string('run_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('mega_scenario_step_logs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code');
            $table->integer('step_index');
            $table->string('step_name');
            $table->string('line_code', 10);
            $table->string('action_taken');
            $table->decimal('financial_impact', 14, 2)->default(0.00);
            $table->string('ledger_reference')->nullable();
            $table->timestamps();

            $table->index(['run_code', 'step_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mega_scenario_step_logs');
        Schema::dropIfExists('mega_scenario_runs');
    }
};
