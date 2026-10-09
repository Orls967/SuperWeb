<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Lakehouse: Outbox CDC Events ──────────────────────────────────────
        Schema::create('dp_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 128)->unique();
            $table->string('source_module', 30);           // EGY|RET|FIN|etc.
            $table->string('entity_type', 50);             // ledger_entry|order|booking
            $table->string('entity_id', 100);
            $table->string('event_type', 50);              // created|updated|deleted
            $table->json('payload');
            $table->string('zone', 20)->default('RAW');    // RAW|CURATED|CONSUMPTION
            $table->string('status', 20)->default('PENDING'); // PENDING|INGESTED|QUARANTINED
            $table->timestamp('occurred_at');
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();
            $table->index(['source_module', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });

        // ─── MDM: Master Entities ──────────────────────────────────────────────
        Schema::create('dp_mdm_entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_category', 30);         // PRODUCT|LOCATION|PARTNER|COA
            $table->string('golden_record_code', 100)->unique(); // Canonical reference
            $table->json('attributes');                    // Merged canonical data
            $table->boolean('is_active')->default(true);
            $table->integer('merge_count')->default(1);    // How many duplicates merged
            $table->timestamp('last_merged_at')->nullable();
            $table->timestamps();
            $table->index(['entity_category', 'is_active']);
        });

        // ─── MDM: Duplicate Detection Log ─────────────────────────────────────
        Schema::create('dp_mdm_duplicates', function (Blueprint $table) {
            $table->id();
            $table->string('entity_category', 30);
            $table->string('candidate_a', 100);
            $table->string('candidate_b', 100);
            $table->string('golden_record_code', 100)->nullable();
            $table->decimal('similarity_score', 5, 4);    // 0.0–1.0
            $table->string('resolution_status', 20)->default('PENDING'); // PENDING|MERGED|REJECTED
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['entity_category', 'resolution_status']);
        });

        // ─── Semantic Metrics Layer: KPI Definitions ───────────────────────────
        Schema::create('dp_kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_code', 50)->unique();      // GMV|ADR|OTIF|UTILIZATION|MARGIN
            $table->string('kpi_name', 100);
            $table->string('line_code', 10)->nullable();   // null = cross-line
            $table->text('sql_definition');                // Canonical SQL for this KPI
            $table->string('source_tables', 500);          // Comma-sep tables used
            $table->string('owner_team', 50)->nullable();
            $table->timestamps();
        });

        // ─── Analytics: Self-Service Dataset Definitions ───────────────────────
        Schema::create('dp_analytics_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_code', 80)->unique();
            $table->string('line_code', 10);
            $table->string('role_scope', 50);              // ADMIN|ANALYST|AUDITOR
            $table->string('row_scope_column', 50)->nullable(); // e.g. line_code
            $table->boolean('export_allowed')->default(false);
            $table->boolean('watermark_on_export')->default(true);
            $table->timestamps();
            $table->index(['line_code', 'role_scope']);
        });

        // ─── Data Quality: Quality Gates ───────────────────────────────────────
        Schema::create('dp_data_quality_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_code', 80)->unique();
            $table->string('domain', 30);                  // EGY|FIN|etc.
            $table->string('check_type', 30);              // COMPLETENESS|FRESHNESS|REFERENTIAL|OUTLIER
            $table->string('status', 20)->default('HEALTHY'); // HEALTHY|WARNING|FAILED
            $table->decimal('quality_score', 5, 2)->default(100.00); // 0–100
            $table->text('finding')->nullable();
            $table->string('owner_email')->nullable();
            $table->boolean('is_quarantined')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['domain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dp_data_quality_checks');
        Schema::dropIfExists('dp_analytics_datasets');
        Schema::dropIfExists('dp_kpi_definitions');
        Schema::dropIfExists('dp_mdm_duplicates');
        Schema::dropIfExists('dp_mdm_entities');
        Schema::dropIfExists('dp_outbox_events');
    }
};
