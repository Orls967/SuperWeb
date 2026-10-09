<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\NatureWaterCommunityFinanceService;
use Tests\TestCase;

class NatureWaterCommunityFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected NatureWaterCommunityFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NatureWaterCommunityFinanceService::class);
    }

    public function test_nature_credit_issuance_and_benefit_share_reconciliation(): void
    {
        // 1. Valid additionality and community consent yields 30% benefit share to local community (330.1 & 330.4)
        $project = $this->service->issueNatureCredits(
            projectCode: 'PROJ-MANGROVE-PAPUA-01',
            projectType: 'MANGROVE_RESTORATION',
            grossProceedsUsd: 1000000.0,
            verifiedAdditionality: true,
            communityConsentGranted: true,
            socialRemediationFiled: false,
            benefitSharePct: 30.00
        );
        $this->assertTrue((bool) $project->issuance_cleared);
        $this->assertEquals(300000.0, (float) $project->disbursed_benefit_share_usd);

        // 2. Issuance without verified additionality is rejected (330.6 Risk)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Additionality failure: Nature credit issuance rejected');
        $this->service->issueNatureCredits('PROJ-GREENWASH', 'MANGROVE', 500000.0, false, true);
    }

    public function test_community_objection_requires_social_remediation(): void
    {
        // 1. Community objection without remediation is blocked (330.5 Edge Case)
        try {
            $this->service->issueNatureCredits(
                projectCode: 'PROJ-CONTESTED-PEATLAND',
                projectType: 'PEATLAND',
                grossProceedsUsd: 800000.0,
                verifiedAdditionality: true,
                communityConsentGranted: false, // Community rejected!
                socialRemediationFiled: false
            );
            $this->fail('Expected exception for unremediated community objection');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Community objection halts project issuance until formal social remediation is filed', $e->getMessage());
        }

        // 2. Community objection resolved with formal social remediation succeeds (330.5)
        $remediated = $this->service->issueNatureCredits(
            projectCode: 'PROJ-REMEDIATED-PEATLAND',
            projectType: 'PEATLAND',
            grossProceedsUsd: 800000.0,
            verifiedAdditionality: true,
            communityConsentGranted: false,
            socialRemediationFiled: true
        );
        $this->assertTrue((bool) $remediated->issuance_cleared);
        $this->assertTrue((bool) $remediated->social_remediation_filed);
    }

    public function test_water_stewardship_independent_measurement_requirement(): void
    {
        // 1. Water savings with independent measurement succeeds (330.2 & 330.4)
        $facility = $this->service->recordWaterStewardshipSavings(
            facilityCode: 'FAC-SMELTER-RECYCLE-01',
            siteCode: 'SITE-SMELTER-POMALAA',
            baselineM3: 50000.0,
            actualM3: 35000.0,
            rateUsdPerM3: 2.00,
            independentlyMeasured: true
        );
        // 15,000 m3 * $2.00 = $30,000
        $this->assertEquals(15000.0, (float) $facility->verified_water_savings_m3);
        $this->assertEquals(30000.0, (float) $facility->performance_payment_usd);

        // 2. Unverified water savings rejected (330.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Measurement verification breach: Water stewardship performance payments require independent meter auditing');
        $this->service->recordWaterStewardshipSavings('FAC-UNVERIFIED', 'SITE-POMALAA', 50000.0, 30000.0, 2.0, false);
    }

    public function test_nature_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->issueNatureCredits('P-AUD', 'MANGROVE', 1000.0, true, true);
        $this->service->recordWaterStewardshipSavings('F-AUD', 'S1', 100.0, 80.0, 1.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: water payment without independent measurement
        DB::table('water_stewardship_performance_facilities')->insert([
            'facility_code' => 'FAC-DEFECT-UNVERIFIED',
            'site_code' => 'S1',
            'metered_baseline_m3' => 1000.0,
            'metered_actual_m3' => 500.0,
            'verified_water_savings_m3' => 500.0,
            'performance_payment_usd' => 1000.0,
            'is_independently_measured' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
