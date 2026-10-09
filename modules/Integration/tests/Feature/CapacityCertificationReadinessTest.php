<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CapacityCertificationReadinessService;
use Tests\TestCase;

class CapacityCertificationReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected CapacityCertificationReadinessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CapacityCertificationReadinessService::class);
    }

    public function test_domain_without_evidence_cannot_certify_edge_case(): void
    {
        // 1. Attempting certification without passing benchmark evidence fails (400.4 & 400.5 Edge Case)
        try {
            $this->service->certifyDomain(
                domainName: 'HEALTHCARE_TELEMETRY',
                hasPassingBenchmarkEvidence: false // Failing evidence!
            );
            $this->fail('Expected exception for unverified domain capacity certification');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('lacks passing benchmark evidence and is strictly blocked from release', $e->getMessage());
        }

        // Verify uncertified and blocked status in DB
        $domain = DB::table('global_stress_domain_capacity_certifications')->where('domain_name', 'HEALTHCARE_TELEMETRY')->first();
        $this->assertNotNull($domain);
        $this->assertFalse((bool) $domain->is_certified);
        $this->assertFalse((bool) $domain->release_permitted);

        // 2. Certification with passing benchmark succeeds (400.1 & 400.4)
        $certified = $this->service->certifyDomain(
            domainName: 'HEALTHCARE_TELEMETRY',
            hasPassingBenchmarkEvidence: true
        );
        $this->assertTrue((bool) $certified->is_certified);
        $this->assertTrue((bool) $certified->release_permitted);
    }

    public function test_super_health_check_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->certifyDomain('LOGISTICS_DISPATCH', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: domain certified without evidence
        DB::table('global_stress_domain_capacity_certifications')->insert([
            'domain_name' => 'DEFECT_DOMAIN',
            'has_passing_benchmark_evidence' => false, // Discrepancy!
            'is_certified' => true, // Discrepancy!
            'release_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
