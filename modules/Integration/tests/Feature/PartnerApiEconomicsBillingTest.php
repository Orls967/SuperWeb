<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\PartnerApiEconomicsBillingService;
use Tests\TestCase;

class PartnerApiEconomicsBillingTest extends TestCase
{
    use RefreshDatabase;

    protected PartnerApiEconomicsBillingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PartnerApiEconomicsBillingService::class);
    }

    public function test_metering_retries_not_double_charged(): void
    {
        // 1. First API call is metered (372.1 & 372.4)
        $first = $this->service->meterApiCall(
            meterCode: 'MTR-CALL-001',
            partnerCode: 'PTN-LOGISTICS-01',
            requestId: 'REQ-UUID-8899-AABB'
        );
        $this->assertEquals(1, $first->billable_calls);

        // 2. Retry of same request ID does not create second record or double-charge (372.4)
        $retry = $this->service->meterApiCall(
            meterCode: 'MTR-CALL-002',
            partnerCode: 'PTN-LOGISTICS-01',
            requestId: 'REQ-UUID-8899-AABB' // Same request ID!
        );
        $this->assertEquals($first->id, $retry->id);
        $this->assertEquals(1, DB::table('partner_api_billing_meterings')->count());
    }

    public function test_quota_overflow_rate_limit_with_upgrade_notice_edge_case(): void
    {
        // Usage exceeds quota: triggers rate limit with upgrade notice, not abrupt cutoff (372.4 & 372.5 Edge Case)
        $evaluation = $this->service->evaluateTierQuota(
            quotaCode: 'QUOTA-EVAL-001',
            partnerCode: 'PTN-LOGISTICS-01',
            monthlyQuota: 10000,
            attemptedUsage: 10450 // Exceeded!
        );

        $this->assertTrue((bool) $evaluation->rate_limited_with_upgrade_notice);
        $this->assertFalse((bool) $evaluation->abruptly_disconnected);
    }

    public function test_api_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->meterApiCall('M-AUD', 'PTN-1', 'REQ-AUD');
        $this->service->evaluateTierQuota('Q-AUD', 'PTN-1', 1000, 500);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: partner abruptly disconnected on quota excess
        DB::table('partner_api_tier_quotas')->insert([
            'quota_code' => 'Q-DEFECT-CUTOFF',
            'partner_code' => 'PTN-1',
            'monthly_quota' => 100,
            'current_usage' => 150,
            'rate_limited_with_upgrade_notice' => false,
            'abruptly_disconnected' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
