<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SupplyChainGovernanceScorecardsService;
use Tests\TestCase;

class SupplyChainGovernanceScorecardsTest extends TestCase
{
    use RefreshDatabase;

    protected SupplyChainGovernanceScorecardsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplyChainGovernanceScorecardsService::class);
    }

    public function test_scorecard_and_corrective_action_flow(): void
    {
        // 414.1 Record scorecard passing targets
        $sc = $this->service->recordScorecard(
            scorecardCode: 'SC-LOG-2026-Q3',
            domain: 'logistics',
            period: '2026-Q3',
            otifRate: 98.50,
            defectPpm: 25.00,
            dataSourceMetric: 'LGX_SHIPMENT_DISPATCH_METRIC'
        );

        $this->assertEquals('SC-LOG-2026-Q3', $sc->scorecard_code);
        $this->assertTrue((bool) $sc->target_met);

        // 414.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_scorecard_miss_requires_verified_corrective_action(): void
    {
        // Scorecard missing OTIF target (91% < 95%)
        $this->service->recordScorecard(
            scorecardCode: 'SC-MFG-FAIL',
            domain: 'manufacturing',
            period: '2026-Q3',
            otifRate: 91.20,
            defectPpm: 450.00, // Defect > 100 ppm
            dataSourceMetric: 'MFG_PRODUCTION_RUN_METRIC'
        );

        // Audit immediately catches unaddressed miss
        $auditMiss = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditMiss['status']);
        $this->assertEquals(1, $auditMiss['unaddressed_misses']);

        // 414.2 Create corrective action
        $action = $this->service->createCorrectiveAction(
            scorecardCode: 'SC-MFG-FAIL',
            actionCode: 'CAPA-2026-08',
            rootCause: 'Die wear on stamping press line 2',
            countermeasure: 'Installed automatic die stroke sensor and replacement interval reduction',
            owner: 'Plant Maintenance Lead'
        );

        $this->assertEquals('open', $action->status);

        // Closing without verified effectiveness is blocked (414.4)
        try {
            $this->service->closeCorrectiveAction('CAPA-2026-08', false);
            $this->fail('Expected exception for unverified closure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires verified effectiveness', $e->getMessage());
        }

        // Close with verified effectiveness
        $closed = $this->service->closeCorrectiveAction('CAPA-2026-08', true);
        $this->assertEquals('closed', $closed->status);
        $this->assertTrue((bool) $closed->effectiveness_verified);

        // Audit clean
        $auditClean = $this->service->audit();
        $this->assertEquals('HEALTHY', $auditClean['status']);
        $this->assertEquals(0, $auditClean['discrepancy_count']);
    }
}
