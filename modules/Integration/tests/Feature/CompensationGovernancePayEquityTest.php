<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CompensationGovernancePayEquityService;
use Tests\TestCase;

class CompensationGovernancePayEquityTest extends TestCase
{
    use RefreshDatabase;

    protected CompensationGovernancePayEquityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CompensationGovernancePayEquityService::class);
    }

    public function test_salary_band_and_merit_review_within_budget(): void
    {
        // 422.1 Salary band
        $this->service->registerSalaryBand(
            bandCode: 'BAND-ENG-L3',
            jobFamily: 'engineering',
            gradeLevel: 'L3_SENIOR',
            min: 20000000.00,
            mid: 28000000.00,
            max: 36000000.00
        );

        // 422.2 Process merit review within 8% budget and within band
        $review = $this->service->processMeritReview(
            reviewCode: 'REV-2026-ENG-01',
            employeeId: 'EMP-7701',
            bandCode: 'BAND-ENG-L3',
            currentSalary: 25000000.00,
            meritPercent: 8.00,
            meritBudgetCap: 10.00
        );

        $this->assertEquals('REV-2026-ENG-01', $review->review_code);
        $this->assertEquals(27000000.00, (float) $review->proposed_salary);
        $this->assertTrue((bool) $review->within_merit_budget);
        $this->assertTrue((bool) $review->within_salary_band);

        // 422.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_budget_breach_blocked_and_equity_remediation_risk(): void
    {
        $this->service->registerSalaryBand('BAND-OPS-L1', 'operations', 'L1', 5000000, 7000000, 9000000);

        // 422.4 & 422.5 Breach merit cap without approval is blocked
        try {
            $this->service->processMeritReview(
                reviewCode: 'REV-EXCEED',
                employeeId: 'EMP-99',
                bandCode: 'BAND-OPS-L1',
                currentSalary: 6000000,
                meritPercent: 25.00, // Exceeds 10% cap!
                meritBudgetCap: 10.00,
                committeeApproval: null
            );
            $this->fail('Expected exception for unapproved merit budget breach');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds merit budget cap', $e->getMessage());
        }

        // 422.3 & 422.6 Pay equity remediation
        $review = $this->service->processMeritReview(
            reviewCode: 'REV-EQUITY-TEST',
            employeeId: 'EMP-100',
            bandCode: 'BAND-OPS-L1',
            currentSalary: 5500000,
            meritPercent: 5.00,
            meritBudgetCap: 10.00
        );

        $this->service->flagUnexplainedEquityGap('REV-EQUITY-TEST');

        // Audit catches unremediated gap
        $auditGap = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditGap['status']);
        $this->assertEquals(1, $auditGap['unremediated_gaps']);

        // Complete remediation
        $this->service->completeEquityRemediation('REV-EQUITY-TEST', 6500000.00);

        // Audit clean
        $auditClean = $this->service->audit();
        $this->assertEquals('HEALTHY', $auditClean['status']);
        $this->assertEquals(0, $auditClean['unremediated_gaps']);
    }
}
