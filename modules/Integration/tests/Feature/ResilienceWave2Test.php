<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ResilienceWave2Service;
use Tests\TestCase;

/**
 * Fase 145 — Resilience Wave 2 Feature Tests
 *
 * (a) Failover tanpa data loss pada ledger
 * (b) Conflict resolution idempoten
 * (c) Edge sync zero-duplicate
 * (d) RTO terukur < target per tier
 * (e) dr:audit = 0 selisih
 */
class ResilienceWave2Test extends TestCase
{
    use RefreshDatabase;

    private ResilienceWave2Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ResilienceWave2Service::class);
        $this->service->seedRegions();
        $this->service->seedBia();
    }

    // ─── (a) Failover Tanpa Data Loss ─────────────────────────────────────────

    public function test_a_dr_drill_passes_with_no_data_loss_on_ledger(): void
    {
        $drill = $this->service->runDrDrill(['HOS', 'FIN', 'EGY'], 'FAILOVER');

        $this->assertSame('PASSED', $drill->status);
        $this->assertFalse((bool) $drill->data_loss_detected);
        $this->assertSame(0, (int) $drill->ledger_discrepancy);
        $this->assertTrue((bool) $drill->audit_clean);
        $this->assertNotNull($drill->completed_at);
    }

    // ─── (b) Conflict Resolution Idempoten ────────────────────────────────────

    public function test_b_conflict_resolution_is_idempotent(): void
    {
        $idempotencyKey = 'LEDGER-ENTRY-IDX-99999-CONFLICT';

        $data = [
            'idempotency_key' => $idempotencyKey,
            'entity_type' => 'ledger_entry',
            'entity_id' => 'LE-99999',
            'winner_region' => 'JKT-PRIMARY',
            'loser_region' => 'SGP-SECONDARY',
            'resolution_strategy' => 'LAST_WRITE_WINS',
            'conflict_data' => ['amount_minor' => 500000000],
        ];

        // First call
        $first = $this->service->resolveConflict($data);
        $this->assertSame('JKT-PRIMARY', $first->winner_region);

        // Second call with same key — must return same record without inserting duplicate
        $second = $this->service->resolveConflict($data);
        $this->assertSame($first->id, $second->id);

        // Verify only 1 record in DB
        $count = \DB::table('res_conflict_resolution_log')
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $this->assertSame(1, $count);
    }

    // ─── (c) Edge Sync Zero-Duplicate ─────────────────────────────────────────

    public function test_c_edge_sync_produces_zero_duplicates(): void
    {
        // Register edge node
        $this->service->registerEdgeNode([
            'node_code' => 'EDGE-MINE-SITE-01',
            'node_type' => 'MINING_SITE',
            'location_ref' => 'MINE-SULAWESI-SITE-1',
        ]);

        // Queue events while offline (same ref = idempotent)
        $this->service->queueEdgeEvent('EDGE-MINE-SITE-01', 'weighbridge.record', ['ref' => 'WB-001', 'tonnes' => 250.5]);
        $this->service->queueEdgeEvent('EDGE-MINE-SITE-01', 'weighbridge.record', ['ref' => 'WB-002', 'tonnes' => 183.2]);
        // Duplicate of WB-001
        $this->service->queueEdgeEvent('EDGE-MINE-SITE-01', 'weighbridge.record', ['ref' => 'WB-001', 'tonnes' => 250.5]);

        // Should only have 2 unique events
        $pendingCount = \DB::table('res_edge_sync_queue')
            ->where('edge_node_code', 'EDGE-MINE-SITE-01')
            ->where('status', 'PENDING')
            ->count();
        $this->assertSame(2, $pendingCount);

        // Sync — no duplicates to core
        $syncResult = $this->service->syncEdgeNode('EDGE-MINE-SITE-01');
        $this->assertSame(2, $syncResult['synced']);
        $this->assertSame(0, $syncResult['duplicate']);

        // All events now SYNCED
        $stillPending = \DB::table('res_edge_sync_queue')
            ->where('edge_node_code', 'EDGE-MINE-SITE-01')
            ->where('status', 'PENDING')
            ->count();
        $this->assertSame(0, $stillPending);
    }

    // ─── (d) RTO Terukur < Target Per Tier ────────────────────────────────────

    public function test_d_actual_rto_within_tier_target_for_critical_lines(): void
    {
        // CRITICAL: HOS/FIN/EGY → RTO target = 15m
        $drill = $this->service->runDrDrill(['HOS', 'FIN', 'EGY'], 'FAILOVER');

        // Actual RTO should be 70% of target (15m) = ~10.5m → 10m
        $this->assertLessThanOrEqual(15, $drill->actual_rto_minutes);
        $this->assertGreaterThan(0, $drill->actual_rto_minutes);
        $this->assertSame('PASSED', $drill->status);
    }

    public function test_d_actual_rto_within_tier_target_for_standard_lines(): void
    {
        // STANDARD: EDU → RTO target = 120m
        $drill = $this->service->runDrDrill(['EDU', 'MED', 'HTL'], 'CHAOS');

        $this->assertLessThanOrEqual(120, $drill->actual_rto_minutes);
        $this->assertSame('PASSED', $drill->status);
    }

    // ─── (e) dr:audit = 0 Selisih ─────────────────────────────────────────────

    public function test_e_dr_audit_returns_zero_discrepancy_after_passed_drills(): void
    {
        // Run drills for all critical lines
        $this->service->runDrDrill(['HOS', 'FIN', 'EGY', 'RET', 'TLX', 'INT'], 'FAILOVER');

        // Register sovereignty rules (compliant)
        $this->service->registerSovereigntyRule([
            'dataset_code' => 'DS-HOS-EMR-001',
            'line_code' => 'HOS',
            'data_classification' => 'CITIZEN_PII',
            'required_residency' => 'ID',
            'current_region' => 'ID',
            'transfer_basis' => null,
        ]);

        $this->service->registerSovereigntyRule([
            'dataset_code' => 'DS-INT-ORCHESTRATE-001',
            'line_code' => 'INT',
            'data_classification' => 'GENERIC',
            'required_residency' => 'ANY',
            'current_region' => 'SGP',
            'transfer_basis' => 'Operational efficiency',
        ]);

        $audit = $this->service->drAudit();

        $this->assertSame(0, $audit['discrepancy_count']);
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['failed_dr_drills']);
        $this->assertSame(0, $audit['drills_with_discrepancy']);
        $this->assertSame(0, $audit['sovereignty_violations']);
    }

    // ─── dr:audit Command ─────────────────────────────────────────────────────

    public function test_dr_audit_command_passes_with_healthy_state(): void
    {
        $this->service->runDrDrill(['HOS', 'FIN', 'EGY'], 'FAILOVER');

        $this->artisan('dr:audit')->assertExitCode(0);
    }

    // ─── Data Sovereignty ─────────────────────────────────────────────────────

    public function test_data_sovereignty_violation_detected_for_wrong_region(): void
    {
        $rule = $this->service->registerSovereigntyRule([
            'dataset_code' => 'DS-HOS-SENSITIVE-999',
            'line_code' => 'HOS',
            'data_classification' => 'MEDICAL',
            'required_residency' => 'ID',
            'current_region' => 'SGP', // Wrong region
        ]);

        $this->assertFalse((bool) $rule->is_compliant);

        $audit = $this->service->drAudit();
        $this->assertGreaterThan(0, $audit['sovereignty_violations']);
    }

    public function test_bia_seeded_for_all_17_lines(): void
    {
        $count = \DB::table('res_bia_records')->count();
        $this->assertSame(17, $count);

        // Verify CRITICAL tier present for key lines
        $critical = \DB::table('res_bia_records')->where('criticality_tier', 'CRITICAL')->get();
        $criticalCodes = $critical->pluck('line_code')->toArray();
        $this->assertContains('HOS', $criticalCodes);
        $this->assertContains('FIN', $criticalCodes);
        $this->assertContains('EGY', $criticalCodes);

        // Verify CRITICAL RTO ≤ 30m (most critical = 15m, some critical = 30m e.g. RET/TLX)
        foreach ($critical as $bia) {
            $this->assertLessThanOrEqual(30, $bia->rto_minutes, "CRITICAL line {$bia->line_code} RTO should be ≤30m");
        }

        // Verify the tightest-SLA lines (HOS/FIN/EGY/INT) are ≤ 15m
        $tightest = \DB::table('res_bia_records')
            ->whereIn('line_code', ['HOS', 'FIN', 'EGY', 'INT'])
            ->get();
        foreach ($tightest as $bia) {
            $this->assertLessThanOrEqual(15, $bia->rto_minutes, "Line {$bia->line_code} RTO should be ≤15m");
        }
    }
}
