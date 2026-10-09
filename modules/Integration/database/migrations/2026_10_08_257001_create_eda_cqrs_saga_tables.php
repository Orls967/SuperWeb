<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eda_event_journal', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_id')->index();
            $table->integer('sequence_number');
            $table->string('event_type');
            $table->json('payload_json');
            $table->timestamps();
        });

        Schema::create('eda_cqrs_projections', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_id')->unique();
            $table->integer('total_booked_count')->default(0);
            $table->decimal('total_amount_usd', 15, 2)->default(0.00);
            $table->integer('rebuilt_count')->default(0);
            $table->boolean('has_divergence')->default(false); // 257.5
            $table->text('divergence_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('eda_saga_executions', function (Blueprint $table) {
            $table->id();
            $table->string('saga_code')->unique();
            $table->string('transaction_type'); // BUNDLE_TRAVEL, SUPPLY_CHAIN, MA_INTEGRATION
            $table->string('current_step');
            $table->string('status')->default('RUNNING'); // RUNNING, COMPLETED, COMPENSATING, COMPENSATED, FAILED
            $table->json('compensation_log_json');
            $table->integer('timeout_seconds')->default(300);
            $table->integer('timeout_policy_version')->default(1); // 257.7
            $table->timestamps();
        });

        Schema::create('eda_schema_compatibility_gates', function (Blueprint $table) {
            $table->id();
            $table->string('event_schema_name');
            $table->integer('schema_version');
            $table->boolean('is_breaking_change')->default(false);
            $table->integer('deprecation_window_days')->default(90); // 257.3, 257.6
            $table->boolean('gate_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eda_schema_compatibility_gates');
        Schema::dropIfExists('eda_saga_executions');
        Schema::dropIfExists('eda_cqrs_projections');
        Schema::dropIfExists('eda_event_journal');
    }
};
