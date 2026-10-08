<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\BoardManagementReportingIntegrityService;
use Tests\TestCase;

class BoardManagementReportingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected BoardManagementReportingIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BoardManagementReportingIntegrityService::class);
    }

    public function test_report_pack_creation_certification_and_publication(): void
    {
        // 405.1 Create report pack
        $pack = $this->service->createReportPack(
            packCode: 'BOD-PACK-2026-Q3',
            period: '2026-Q3',
            title: 'Q3 Enterprise Financial & Operational Performance',
            reportedRevenue: 15000000000.00,
            ledgerVerifiedRevenue: 15000000000.00,
            narrativeSummary: 'Strong revenue expansion across all lines driven by fintech & logistics.',
            preparer: 'Financial Controller'
        );

        $this->assertEquals('BOD-PACK-2026-Q3', $pack->pack_code);
        $this->assertFalse((bool) $pack->is_certified);

        // 405.4 Attempting publication before certification fails
        try {
            $this->service->publishReportPack('BOD-PACK-2026-Q3');
            $this->fail('Expected exception for uncertified pack publication');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('is not certified', $e->getMessage());
        }

        // 405.2 Certify with proper segregation of duties
        $certified = $this->service->certifyReportPack(
            packCode: 'BOD-PACK-2026-Q3',
            reviewer: 'VP Finance',
            approver: 'Chief Financial Officer'
        );

        $this->assertTrue((bool) $certified->is_certified);

        // Publish
        $published = $this->service->publishReportPack('BOD-PACK-2026-Q3');
        $this->assertTrue((bool) $published->is_published);

        // 405.4 Restatement test preserving version
        $restated = $this->service->restateReportPack(
            packCode: 'BOD-PACK-2026-Q3',
            newRevenue: 15200000000.00,
            reason: 'Post-close logistics billings adjustment',
            approvedBy: 'Audit Committee Chair'
        );

        $this->assertEquals(2, $restated->version);
        $this->assertEquals(15200000000.00, (float) $restated->reported_revenue);

        // 405.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_revenue_divergence_blocks_certification_edge_case(): void
    {
        // 405.5 Edge case: narrative revenue does not match ledger verified revenue
        $this->service->createReportPack(
            packCode: 'BOD-PACK-DIVERGENT',
            period: '2026-Q3',
            title: 'Flawed Report',
            reportedRevenue: 20000000000.00,
            ledgerVerifiedRevenue: 18000000000.00, // 2B mismatch!
            narrativeSummary: 'Optimistic manual projection',
            preparer: 'Analyst A'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integrity mismatch: Reported revenue does not reconcile with ledger-verified revenue');

        $this->service->certifyReportPack(
            packCode: 'BOD-PACK-DIVERGENT',
            reviewer: 'Manager B',
            approver: 'Director C'
        );
    }
}
