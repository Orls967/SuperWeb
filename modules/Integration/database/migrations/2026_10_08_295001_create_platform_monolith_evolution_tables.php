<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_architecture_fitness_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code')->unique();
            $table->string('fitness_type'); // NO_CROSS_DOMAIN_DIRECT_DB, CONTRACT_ONLY_INTEGRATION, NO_CIRCULAR_DEPENDENCIES
            $table->boolean('enforced_in_ci')->default(true); // 295.1 & 295.5
            $table->boolean('is_violated')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_schema_evolution_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->string('rehearsal_code')->unique();
            $table->string('migration_phase'); // EXPAND, DUAL_WRITE, BACKFILL, CUTOVER, CLEANUP
            $table->integer('seeded_records_count');
            $table->integer('data_loss_count')->default(0); // 295.2 & 295.5 Must be 0
            $table->boolean('is_rehearsal_successful')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_microservice_extraction_adrs', function (Blueprint $table) {
            $table->id();
            $table->string('adr_code')->unique();
            $table->string('target_module_name');
            $table->text('evidence_benchmark_summary'); // 295.3, 295.5, 295.7 Required evidence
            $table->boolean('board_architect_approved')->default(false);
            $table->string('extraction_status')->default('PROPOSED'); // PROPOSED, APPROVED, REJECTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_microservice_extraction_adrs');
        Schema::dropIfExists('platform_schema_evolution_rehearsals');
        Schema::dropIfExists('platform_architecture_fitness_rules');
    }
};
