<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_future_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->string('title');
            $table->string('time_horizon'); // e.g. 2030, 2035 (482.1, 482.2)
            $table->decimal('option_investment_idr', 18, 2)->default(0.00); // 482.2, 482.4
            $table->string('trigger_monitoring_owner'); // 482.6 risk
            $table->string('monitoring_cadence'); // e.g. quarterly
            $table->boolean('assumptions_reviewed')->default(false); // 482.5 edge case
            $table->string('status')->default('active_monitoring'); // active_monitoring, triggered, archived
            $table->timestamps();
        });

        Schema::create('int_enterprise_innovation_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('pipeline_code')->unique();
            $table->integer('ideas_count')->default(0);
            $table->integer('experiments_count')->default(0);
            $table->integer('pilots_count')->default(0);
            $table->integer('scaled_solutions_count')->default(0); // 482.3
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_enterprise_innovation_pipelines');
        Schema::dropIfExists('int_enterprise_future_scenarios');
    }
};
