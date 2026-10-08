<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PlatformMonolithEvolutionService;
use Tests\TestCase;

class PlatformMonolithEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformMonolithEvolutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlatformMonolithEvolutionService::class);
    }

    public function test_architecture_fitness_violation_strictly_fails(): void
    {
        // 1. Clean contract/event interaction passes (295.1 & 295.5)
        $clean = $this->service->evaluateArchitectureFitness('FIT-INVENTORY-CORE', false);
        $this->assertFalse((bool) $clean->is_violated);

        // 2. Direct cross-domain DB query strictly fails (295.1 & 295.5)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture fitness violation: Direct cross-domain database querying is strictly prohibited');
        $this->service->evaluateArchitectureFitness('FIT-BILLING-DIRECT-INVOICE', true);
    }

    public function test_schema_evolution_rehearsal_requires_zero_data_loss(): void
    {
        // 1. Successful rehearsal with 0 data loss (295.2 & 295.5)
        $rehearsal = $this->service->executeMigrationRehearsal(
            rehearsalCode: 'REH-EXPAND-CONTRACT-PAYMENTS-01',
            phase: 'CUTOVER',
            seededCount: 5000000,
            dataLossCount: 0
        );
        $this->assertTrue((bool) $rehearsal->is_rehearsal_successful);
        $this->assertEquals(0, (int) $rehearsal->data_loss_count);

        // 2. Rehearsal resulting in any data loss (> 0) fails (295.5)
        try {
            $this->service->executeMigrationRehearsal('REH-FAULTY', 'CUTOVER', 10000, 3);
            $this->fail('Expected exception for data loss during migration rehearsal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds 0 tolerance threshold', $e->getMessage());
        }
    }

    public function test_microservice_extraction_requires_empirical_adr_evidence(): void
    {
        // 1. Propose extraction backed by empirical benchmark evidence (295.3, 295.5, 295.7)
        $adr = $this->service->proposeExtractionAdr(
            adrCode: 'ADR-EXTRACT-ROBOTICS-TELEMETRY-01',
            targetModule: 'WAREHOUSE_ROBOTICS',
            evidenceSummary: 'Benchmark proves 250,000 writes/sec saturates main OLTP connection pool; isolating reduces tail latency by 85%.',
            architectApproved: true
        );
        $this->assertEquals('APPROVED', $adr->extraction_status);
        $this->assertTrue((bool) $adr->board_architect_approved);

        // 2. Extraction attempt without empirical benchmark rejected (295.7)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extraction requires documented empirical benchmark evidence');
        $this->service->proposeExtractionAdr('ADR-HYPE', 'CORE_OMS', '');
    }

    public function test_platform_evolution_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluateArchitectureFitness('FIT-AUD', false);
        $this->service->executeMigrationRehearsal('REH-AUD', 'EXPAND', 1000, 0);
        $this->service->proposeExtractionAdr('ADR-AUD', 'MOD', 'Sufficient empirical evidence provided here.', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: failed migration rehearsal with data loss
        DB::table('platform_schema_evolution_rehearsals')->insert([
            'rehearsal_code' => 'REH-FAILED-LEAK',
            'migration_phase' => 'CUTOVER',
            'seeded_records_count' => 1000,
            'data_loss_count' => 5, // Discrepancy!
            'is_rehearsal_successful' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
