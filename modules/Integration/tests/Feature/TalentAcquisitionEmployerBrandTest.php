<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\TalentAcquisitionEmployerBrandService;
use Tests\TestCase;

class TalentAcquisitionEmployerBrandTest extends TestCase
{
    use RefreshDatabase;

    protected TalentAcquisitionEmployerBrandService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TalentAcquisitionEmployerBrandService::class);
    }

    public function test_requisition_and_candidate_screening_flow(): void
    {
        // 421.1 & 421.2 Create high-volume requisition
        $req = $this->service->createRequisition(
            code: 'REQ-TECH-2026-01',
            roleFamily: 'engineering',
            headcount: 20,
            sourceChannel: 'campus_recruiting',
            costPerHire: 2500000.00,
            timeToFillDays: 14
        );

        $this->assertEquals('REQ-TECH-2026-01', $req->requisition_code);

        // Process candidate with fairness check verified
        $app = $this->service->processCandidateApplication(
            reqCode: 'REQ-TECH-2026-01',
            appCode: 'APP-CAND-001',
            candidateName: 'Budi Santoso',
            score: 85.50,
            fairnessBiasChecked: true
        );

        $this->assertEquals('offered', $app->status);
        $this->assertTrue((bool) $app->fairness_bias_checked);

        // 421.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_automated_screening_bias_blocked_edge_case(): void
    {
        $this->service->createRequisition('REQ-DRIVER-02', 'logistics_driver', 50, 'jobstreet');

        // 421.5 Edge case: Screening without certified fairness bias check is blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Screening blocked: Automated screening lacks certified fairness bias check');

        $this->service->processCandidateApplication(
            reqCode: 'REQ-DRIVER-02',
            appCode: 'APP-CAND-UNCHECKED',
            candidateName: 'Rian Pratama',
            score: 75.00,
            fairnessBiasChecked: false // Missing!
        );
    }
}
