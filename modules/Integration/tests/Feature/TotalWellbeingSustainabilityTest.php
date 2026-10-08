<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\TotalWellbeingSustainabilityService;
use Tests\TestCase;

class TotalWellbeingSustainabilityTest extends TestCase
{
    use RefreshDatabase;

    protected TotalWellbeingSustainabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TotalWellbeingSustainabilityService::class);
    }

    public function test_burnout_risk_indicator_and_mandatory_rest_break_enforcement(): void
    {
        // 1. Moderate workload yields GREEN indicator (320.1 & 320.4)
        $green = $this->service->assessBurnoutRisk('EMP-OPS-01', 10.0, 95.0);
        $this->assertEquals('GREEN', $green->burnout_indicator);
        $this->assertFalse((bool) $green->mandatory_break_and_redistribution_enforced);

        // 2. Excessive overtime (> 40h) triggers RED indicator and mandates rest break (320.1 & 320.5 Edge Case)
        $red = $this->service->assessBurnoutRisk('EMP-OVERWORKED-02', 45.0, 130.0);
        $this->assertEquals('RED', $red->burnout_indicator);
        $this->assertTrue((bool) $red->mandatory_break_and_redistribution_enforced);
    }

    public function test_safety_culture_leading_index_calculation(): void
    {
        // 1. High reporting site (12 near-misses, 4 stop-works = 50 + 24 + 20 = 94.0 >= 75.0 target) (320.3 & 320.4)
        $safeSite = $this->service->computeSafetyCultureIndex('SITE-MINING-PIT-01', 12, 4, 75.0);
        $this->assertEquals(94.0, (float) $safeSite->safety_culture_leading_index);
        $this->assertTrue((bool) $safeSite->safety_standard_met);

        // 2. Poor reporting site (2 near-misses, 0 stop-works = 50 + 4 = 54.0 < 75.0 target) flags standard failure (320.3)
        $subparSite = $this->service->computeSafetyCultureIndex('SITE-REFINERY-LAGGING', 2, 0, 75.0);
        $this->assertEquals(54.0, (float) $subparSite->safety_culture_leading_index);
        $this->assertFalse((bool) $subparSite->safety_standard_met);
    }

    public function test_hcm_wellbeing_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->assessBurnoutRisk('EMP-AUD', 5.0, 80.0);
        $this->service->computeSafetyCultureIndex('SITE-AUD', 15, 3, 75.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: RED burnout without enforced break
        DB::table('workforce_burnout_risk_profiles')->insert([
            'employee_id' => 'EMP-IGNORED-BURNOUT',
            'overtime_hours_month' => 60.0,
            'utilization_rate_pct' => 140.0,
            'burnout_indicator' => 'RED',
            'mandatory_break_and_redistribution_enforced' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
