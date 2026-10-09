<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EnterpriseResilienceAntiFragilityService;
use Tests\TestCase;

class EnterpriseResilienceAntiFragilityTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseResilienceAntiFragilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseResilienceAntiFragilityService::class);
    }

    public function test_resilience_index_and_continuous_improvement_flow(): void
    {
        // 479.1 Calculate resilience index with empirical drill verification
        $res = $this->service->calculateResilienceIndex(
            domain: 'DOM-CORE-BANKING-PAYMENTS',
            recovery: 90.00,
            redundancy: 95.00,
            diversity: 85.00,
            learningRate: 88.00,
            drillVerified: true
        );

        $this->assertEquals('DOM-CORE-BANKING-PAYMENTS', $res->domain_code);
        $this->assertTrue((bool) $res->empirically_drill_verified);
        $this->assertGreaterThan(85.00, (float) $res->composite_resilience_index);

        // 479.2 & 479.6 Intake improvement idea and standardize
        $idea = $this->service->intakeImprovementIdea(
            code: 'IDEA-REDUCE-HOTSPOT',
            domain: 'DOM-CORE-BANKING-PAYMENTS',
            description: 'Implement distributed redis cache cluster on checkout token verification',
            slaDueDate: now()->addDays(30)->toDateString()
        );

        $this->assertEquals('intake', $idea->status);

        $closed = $this->service->standardizeAndCloseIdea('IDEA-REDUCE-HOTSPOT');
        $this->assertEquals('closed', $closed->status);

        // 479.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unverified_high_resilience_index_discrepancy_edge_case(): void
    {
        // 479.5 Edge case: High resilience index without empirical drill verification is flagged in audit
        $this->service->calculateResilienceIndex(
            domain: 'DOM-UNTESTED-CLOUD',
            recovery: 95.00,
            redundancy: 90.00,
            diversity: 90.00,
            learningRate: 85.00,
            drillVerified: false // Unverified paper index!
        );

        $audit = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $audit['status']);
        $this->assertGreaterThan(0, $audit['discrepancy_count']);

        // Verify via drill resolves audit discrepancy
        $this->service->verifyResilienceViaDrill('DOM-UNTESTED-CLOUD');
        $cleanAudit = $this->service->audit();
        $this->assertEquals('HEALTHY', $cleanAudit['status']);
    }
}
