<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_conglomerate_runs', function (Blueprint $table) {
            $table->id();
            $table->string('simulation_code')->unique();
            $table->string('seed');
            $table->integer('days_simulated')->default(365); // 465.1
            $table->integer('lines_participating')->default(30);
            $table->string('state_hash'); // 465.3 determinism proof
            $table->integer('total_audits_passed')->default(0); // 465.2 target 100+
            $table->integer('audit_variance_count')->default(0); // 465.2 must be 0
            $table->boolean('query_budget_exceeded')->default(false); // 465.6 risk
            $table->string('status')->default('running'); // running, completed, aborted_discrepancy
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_conglomerate_runs');
    }
};
