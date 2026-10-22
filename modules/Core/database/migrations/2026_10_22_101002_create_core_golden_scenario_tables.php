<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_golden_scenario_runs')) {
            Schema::create('core_golden_scenario_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('run_uuid')->unique();
                $table->string('scenario_name', 128);
                $table->integer('total_steps')->default(12);
                $table->integer('passed_steps')->default(0);
                $table->integer('failed_steps')->default(0);
                $table->integer('total_journal_entries')->default(0);
                $table->bigInteger('total_amount_idr')->default(0);
                $table->boolean('is_balanced')->default(false);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['scenario_name', 'is_balanced']);
            });
        }

        if (! Schema::hasTable('core_golden_scenario_steps')) {
            Schema::create('core_golden_scenario_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->constrained('core_golden_scenario_runs')->cascadeOnDelete();
                $table->integer('step_number');
                $table->string('business_line_code', 32);
                $table->string('step_name', 128);
                $table->string('actor_email', 128);
                $table->bigInteger('amount_idr')->default(0);
                $table->string('execution_status', 32)->default('SUCCESS');
                $table->string('trace_hash', 64);
                $table->timestamps();

                $table->index(['run_id', 'step_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_golden_scenario_steps');
        Schema::dropIfExists('core_golden_scenario_runs');
    }
};
