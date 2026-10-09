<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_quality_gate_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_code')->unique();
            $table->decimal('unit_coverage_percent', 5, 2);
            $table->decimal('contract_coverage_percent', 5, 2);
            $table->decimal('integration_coverage_percent', 5, 2);
            $table->decimal('e2e_coverage_percent', 5, 2);
            $table->decimal('mutation_kill_rate_percent', 5, 2); // 426.3, 426.4
            $table->boolean('passed_all_gates')->default(false); // 426.1, 426.4
            $table->timestamps();
        });

        Schema::create('plt_flaky_test_quarantine', function (Blueprint $table) {
            $table->id();
            $table->string('test_signature')->unique();
            $table->string('module_code');
            $table->string('owner');
            $table->integer('quarantine_days')->default(0);
            $table->boolean('is_flagged_as_defect')->default(false); // 426.5 edge case (> 14 days)
            $table->string('status')->default('quarantined'); // quarantined, resolved, active_defect
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_flaky_test_quarantine');
        Schema::dropIfExists('plt_quality_gate_modules');
    }
};
