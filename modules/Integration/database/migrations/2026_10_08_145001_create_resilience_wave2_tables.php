<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Active-Active Multi-Region: Region Registry ───────────────────────
        Schema::create('res_regions', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 20)->unique();  // JKT-PRIMARY, SGP-SECONDARY
            $table->string('region_name', 100);
            $table->string('role', 20);                   // PRIMARY|SECONDARY|EDGE
            $table->boolean('is_active')->default(true);
            $table->string('dns_weight')->nullable();      // DNS geo-routing weight
            $table->timestamps();
        });

        // ─── Active-Active: Conflict Resolution Log ─────────────────────────────
        Schema::create('res_conflict_resolution_log', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 128)->unique();
            $table->string('entity_type', 50);            // ledger_entry|inventory|order
            $table->string('entity_id', 100);
            $table->string('winner_region', 20);
            $table->string('loser_region', 20);
            $table->string('resolution_strategy', 30);    // LAST_WRITE_WINS|MERGE|REJECT_DUPLICATE
            $table->json('conflict_data')->nullable();
            $table->timestamp('resolved_at');
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        // ─── Edge Compute: Edge Node Registry ──────────────────────────────────
        Schema::create('res_edge_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_code', 50)->unique();    // EDGE-VENUE-STADIUM-01
            $table->string('node_type', 30);              // VENUE|MINING_SITE|HOTEL|EVENT
            $table->string('location_ref', 100);          // Venue/Site ID it serves
            $table->string('status', 20)->default('ONLINE'); // ONLINE|OFFLINE|SYNCING
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('pending_sync_count')->default(0);
            $table->timestamps();
            $table->index(['node_type', 'status']);
        });

        // ─── Edge Compute: Offline Queue for sync ──────────────────────────────
        Schema::create('res_edge_sync_queue', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 128)->unique();
            $table->string('edge_node_code', 50);
            $table->string('event_type', 50);             // ticket.scanned|meter.reading|order.placed
            $table->json('payload');
            $table->string('status', 20)->default('PENDING'); // PENDING|SYNCED|DUPLICATE
            $table->timestamp('created_locally_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['edge_node_code', 'status']);
        });

        // ─── Business Continuity: BIA (Business Impact Analysis) ───────────────
        Schema::create('res_bia_records', function (Blueprint $table) {
            $table->id();
            $table->string('line_code', 10);
            $table->string('criticality_tier', 20);       // CRITICAL|STANDARD|LOW
            $table->integer('rto_minutes');               // Recovery Time Objective
            $table->integer('rpo_minutes');               // Recovery Point Objective
            $table->text('impact_description')->nullable();
            $table->timestamps();
            $table->unique(['line_code']);
        });

        // ─── Business Continuity: DR Drill Results ─────────────────────────────
        Schema::create('res_dr_drill_results', function (Blueprint $table) {
            $table->id();
            $table->string('drill_code', 80)->unique();
            $table->string('drill_type', 30);             // FAILOVER|CHAOS|REGION_LOSS
            $table->json('lines_tested');
            $table->string('status', 20)->default('RUNNING'); // RUNNING|PASSED|FAILED
            $table->integer('actual_rto_minutes')->nullable();
            $table->boolean('data_loss_detected')->default(false);
            $table->integer('ledger_discrepancy')->default(0);
            $table->boolean('audit_clean')->default(false);
            $table->text('findings')->nullable();
            $table->timestamp('drilled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['drill_type', 'status']);
        });

        // ─── Data Sovereignty: Dataset Placement Rules ─────────────────────────
        Schema::create('res_data_sovereignty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_code', 80)->unique();
            $table->string('line_code', 10);
            $table->string('data_classification', 30);    // MEDICAL|CITIZEN_PII|FINANCIAL|GENERIC
            $table->string('required_residency', 20);     // ID|SG|ANY
            $table->string('current_region', 20);
            $table->boolean('is_compliant')->default(true);
            $table->text('transfer_basis')->nullable();    // Contractual clause / consent
            $table->timestamps();
            $table->index(['line_code', 'is_compliant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('res_data_sovereignty_rules');
        Schema::dropIfExists('res_dr_drill_results');
        Schema::dropIfExists('res_bia_records');
        Schema::dropIfExists('res_edge_sync_queue');
        Schema::dropIfExists('res_edge_nodes');
        Schema::dropIfExists('res_conflict_resolution_log');
        Schema::dropIfExists('res_regions');
    }
};
