<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\MnaMegaScenarioRestructuringService;
use Tests\TestCase;

class MnaMegaScenarioRestructuringTest extends TestCase
{
    use RefreshDatabase;

    protected MnaMegaScenarioRestructuringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MnaMegaScenarioRestructuringService::class);
    }

    public function test_mna_integration_and_idempotent_migration_flow(): void
    {
        // 467.1 Initiate integration of simulated 3-module entity
        $event = $this->service->initiateIntegration('MNA-INT-2026-01', 'ENTITY-TARGET-CORP');
        $this->assertEquals('initiated', $event->status);

        // 467.2 & 467.4 Execute idempotent migration
        $completed1 = $this->service->executeIdempotentMigration('MNA-INT-2026-01', 4500);
        $this->assertEquals('completed', $completed1->status);
        $this->assertEquals(4500, $completed1->migrated_records_count);

        // Re-execute migration to verify idempotency (deterministic count, no duplicates)
        $completed2 = $this->service->executeIdempotentMigration('MNA-INT-2026-01', 4500);
        $this->assertEquals(4500, $completed2->migrated_records_count);
        $this->assertTrue((bool) $completed2->is_idempotent);
        $this->assertTrue((bool) $completed2->audit_trail_preserved);

        // 467.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_clean_rollback_on_migration_failure_edge_case(): void
    {
        // 467.5 Edge case: Clean rollback without residual duplicate master data
        $this->service->initiateIntegration('MNA-INT-FAIL-01', 'TARGET-FLAKY');
        $rolledBack = $this->service->rollbackFailedMigration('MNA-INT-FAIL-01');

        $this->assertEquals('rolled_back_clean', $rolledBack->status);
        $this->assertEquals(0, $rolledBack->migrated_records_count);
        $this->assertFalse((bool) $rolledBack->duplicate_master_data_detected);

        // Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }
}
