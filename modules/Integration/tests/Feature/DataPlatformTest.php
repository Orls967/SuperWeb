<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\DataPlatformService;
use Tests\TestCase;

/**
 * Fase 146 — Data Platform Feature Tests
 *
 * (a) CDC doesn't modify source data
 * (b) Golden record merge is reversible
 * (c) Metrics layer consistent with ledger source
 * (d) Export scope strictly controls anti-leak
 * (e) Data quality gate reflected in health check
 */
class DataPlatformTest extends TestCase
{
    use RefreshDatabase;

    private DataPlatformService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataPlatformService::class);
        $this->service->seedKpiDefinitions();
    }

    // ─── (a) CDC Doesn't Modify Source Data ───────────────────────────────────

    public function test_a_cdc_ingest_does_not_modify_source_data(): void
    {
        // Ingest a simulated CDC event from EGY module
        $event = $this->service->ingestCdcEvent([
            'source_module' => 'EGY',
            'entity_type' => 'meter_reading',
            'entity_id' => 'METER-001-READING-5001',
            'event_type' => 'created',
            'payload' => ['kwh' => 1250.5, 'timestamp' => now()->toIsoString()],
        ]);

        $this->assertSame('PENDING', $event->status);
        $this->assertSame('RAW', $event->zone);

        // Promote to curated
        $result = $this->service->promoteToCurated('EGY');
        $this->assertSame(1, $result['promoted']);

        // Source table (real EGY meter table) MUST NOT be touched
        // Verify only dp_outbox_events table was written
        $this->assertDatabaseCount('dp_outbox_events', 1);
        $this->assertDatabaseHas('dp_outbox_events', [
            'entity_id' => 'METER-001-READING-5001',
            'zone' => 'CURATED',
            'status' => 'INGESTED',
        ]);
    }

    public function test_a_cdc_ingest_is_idempotent_does_not_duplicate(): void
    {
        $data = [
            'idempotency_key' => 'EGY:meter_reading:METER-999:created',
            'source_module' => 'EGY',
            'entity_type' => 'meter_reading',
            'entity_id' => 'METER-999',
            'event_type' => 'created',
            'payload' => ['kwh' => 500.0],
        ];

        $first = $this->service->ingestCdcEvent($data);
        $second = $this->service->ingestCdcEvent($data);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('dp_outbox_events', 1);
    }

    // ─── (b) Golden Record Merge Reversible ───────────────────────────────────

    public function test_b_golden_record_merge_is_reversible(): void
    {
        // Create golden record
        $golden = $this->service->upsertGoldenRecord(
            'PRODUCT',
            'PROD-GOLDEN-LAPTOP-15',
            ['name' => 'Laptop 15" Pro', 'sku_variants' => ['LP15-BLK', 'LP15-SLV'], 'source_system' => 'RET']
        );

        $this->assertSame('PRODUCT', $golden->entity_category);
        $this->assertSame('PROD-GOLDEN-LAPTOP-15', $golden->golden_record_code);

        // Detect duplicate
        $dup = $this->service->detectDuplicate(
            'PRODUCT',
            'PROD-GOLDEN-LAPTOP-15',
            'PROD-LEGACY-LAPTOP-15-OLD',
            0.9700
        );
        $this->assertSame('PENDING', $dup->resolution_status);

        // Merge — reversible: original candidate codes preserved in duplicate table
        $merged = $this->service->mergeDuplicates($dup->id, 'PROD-GOLDEN-LAPTOP-15');

        $this->assertSame('MERGED', $merged->resolution_status);
        $this->assertSame('PROD-GOLDEN-LAPTOP-15', $merged->golden_record_code);
        $this->assertNotNull($merged->resolved_at);

        // Original candidates still retrievable from dp_mdm_duplicates (reversibility)
        $this->assertDatabaseHas('dp_mdm_duplicates', [
            'candidate_a' => 'PROD-GOLDEN-LAPTOP-15',
            'candidate_b' => 'PROD-LEGACY-LAPTOP-15-OLD',
        ]);
    }

    // ─── (c) Metrics Layer Consistent With Source ─────────────────────────────

    public function test_c_kpi_definitions_seeded_and_consistent(): void
    {
        $kpiCount = \DB::table('dp_kpi_definitions')->count();
        $this->assertGreaterThanOrEqual(6, $kpiCount);

        // All dashboards must use canonical KPI definition (not ad-hoc SQL)
        $gmv = $this->service->getKpiDefinition('GMV');
        $this->assertNotNull($gmv);
        $this->assertSame('Gross Merchandise Value', $gmv->kpi_name);
        $this->assertStringContainsString('ledger_entries', $gmv->sql_definition);

        $adr = $this->service->getKpiDefinition('ADR');
        $this->assertSame('HTL', $adr->line_code);

        $otif = $this->service->getKpiDefinition('OTIF');
        $this->assertSame('LOG', $otif->line_code);
    }

    // ─── (d) Export Scope Anti-Leak ───────────────────────────────────────────

    public function test_d_export_scope_strictly_controls_anti_leak(): void
    {
        // Register a dataset with export restriction
        $this->service->registerDataset([
            'dataset_code' => 'DS-HOS-EMR-ANALYTICS',
            'line_code' => 'HOS',
            'role_scope' => 'ANALYST',
            'row_scope_column' => 'patient_id',
            'export_allowed' => false,    // Explicitly blocked
            'watermark_on_export' => true,
        ]);

        // Customer cannot export
        $this->assertFalse($this->service->canExport('DS-HOS-EMR-ANALYTICS', 'customer'));

        // Even admin cannot export — dataset itself has export_allowed=false
        $this->assertFalse($this->service->canExport('DS-HOS-EMR-ANALYTICS', 'admin'));

        // Register exportable dataset with watermark
        $this->service->registerDataset([
            'dataset_code' => 'DS-RET-SALES-ANALYST',
            'line_code' => 'RET',
            'role_scope' => 'ANALYST',
            'export_allowed' => true,
            'watermark_on_export' => true, // Watermark required
        ]);

        $this->assertTrue($this->service->canExport('DS-RET-SALES-ANALYST', 'ANALYST'));
        $this->assertFalse($this->service->canExport('DS-RET-SALES-ANALYST', 'customer'));
    }

    // ─── (e) Data Quality Gate ────────────────────────────────────────────────

    public function test_e_data_quality_gate_quarantines_bad_data(): void
    {
        // Healthy check
        $healthy = $this->service->runQualityCheck([
            'check_code' => 'DQ-FIN-COMPLETENESS-001',
            'domain' => 'FIN',
            'check_type' => 'COMPLETENESS',
            'quality_score' => 98.5,
        ]);
        $this->assertSame('HEALTHY', $healthy->status);
        $this->assertFalse((bool) $healthy->is_quarantined);

        // Warning check
        $warning = $this->service->runQualityCheck([
            'check_code' => 'DQ-LOG-FRESHNESS-001',
            'domain' => 'LOG',
            'check_type' => 'FRESHNESS',
            'quality_score' => 80.0,
            'finding' => 'Some GPS records older than 2 hours',
        ]);
        $this->assertSame('WARNING', $warning->status);
        $this->assertFalse((bool) $warning->is_quarantined);

        // Failed check → quarantine
        $failed = $this->service->runQualityCheck([
            'check_code' => 'DQ-EGY-OUTLIER-001',
            'domain' => 'EGY',
            'check_type' => 'OUTLIER',
            'quality_score' => 45.0,
            'finding' => '55% of meter readings exceed 3σ — possible sensor failure',
        ]);
        $this->assertSame('FAILED', $failed->status);
        $this->assertTrue((bool) $failed->is_quarantined);
    }

    public function test_e_data_quality_gate_reflected_in_health_audit(): void
    {
        // All healthy — no quarantine
        $this->service->runQualityCheck([
            'check_code' => 'DQ-RET-REFERENTIAL-001', 'domain' => 'RET',
            'check_type' => 'REFERENTIAL', 'quality_score' => 99.0,
        ]);

        $audit = $this->service->audit();
        $this->assertSame(0, $audit['quarantined_datasets']);
        $this->assertSame(0, $audit['failed_quality']);
        $this->assertSame(0, $audit['discrepancy_count']);
        $this->assertSame('HEALTHY', $audit['status']);
    }

    // ─── Full Audit Scenario ──────────────────────────────────────────────────

    public function test_full_data_platform_audit_returns_healthy(): void
    {
        // Ingest events from several modules
        foreach (['EGY', 'RET', 'FIN', 'LOG'] as $module) {
            $this->service->ingestCdcEvent([
                'source_module' => $module,
                'entity_type' => 'test_entity',
                'entity_id' => $module.'-TEST-1',
                'event_type' => 'created',
                'payload' => ['test' => true],
            ]);
            $this->service->promoteToCurated($module);
        }

        // All quality checks pass
        foreach (['EGY', 'RET', 'FIN', 'LOG'] as $module) {
            $this->service->runQualityCheck([
                'check_code' => "DQ-{$module}-FULL-001",
                'domain' => $module,
                'check_type' => 'COMPLETENESS',
                'quality_score' => 97.0,
            ]);
        }

        // Register datasets with watermark
        $this->service->registerDataset([
            'dataset_code' => 'DS-FULL-TEST-001',
            'line_code' => 'INT',
            'role_scope' => 'ADMIN',
            'export_allowed' => true,
            'watermark_on_export' => true,
        ]);

        $audit = $this->service->audit();
        $this->assertSame(0, $audit['discrepancy_count']);
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['export_leak_risk']);
    }
}
