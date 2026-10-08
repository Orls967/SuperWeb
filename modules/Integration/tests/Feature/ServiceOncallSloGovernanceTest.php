<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ServiceOncallSloGovernanceService;
use Tests\TestCase;

class ServiceOncallSloGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected ServiceOncallSloGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ServiceOncallSloGovernanceService::class);
    }

    public function test_ownerless_or_oncall_less_critical_service_fails_readiness_edge_case(): void
    {
        // 1. Critical service without named owner fails readiness (363.1 & 363.4)
        try {
            $this->service->registerServiceReadiness(
                serviceCode: 'SVC-BILLING-CORE',
                serviceName: 'Core Billing Engine',
                criticalityTier: 'CRITICAL',
                namedOwner: null, // No owner!
                oncallRotationId: 'ROTA-BILLING-24X7'
            );
            $this->fail('Expected exception for ownerless critical service');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must have a designated named owner', $e->getMessage());
        }

        // 2. Critical service without on-call rotation fails readiness (363.4 & 363.5 Edge Case)
        try {
            $this->service->registerServiceReadiness(
                serviceCode: 'SVC-DISPATCH-MINE',
                serviceName: 'Haul Dispatch Realtime Service',
                criticalityTier: 'CRITICAL',
                namedOwner: 'ENGINEER-LEAD-DISPATCH',
                oncallRotationId: null // No on-call!
            );
            $this->fail('Expected exception for critical service without on-call rotation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot release without active on-call rotation', $e->getMessage());
        }

        // 3. Fully staffed critical service passes readiness (363.1 & 363.4)
        $ready = $this->service->registerServiceReadiness(
            serviceCode: 'SVC-CORE-ERP',
            serviceName: 'Enterprise Core ERP Gateway',
            criticalityTier: 'CRITICAL',
            namedOwner: 'LEAD-ERP-DEV',
            oncallRotationId: 'ROTA-ERP-TIER1'
        );
        $this->assertTrue((bool) $ready->readiness_passed);
        $this->assertEquals('LEAD-ERP-DEV', $ready->named_owner);
    }

    public function test_slo_error_budget_exhaustion_release_freeze(): void
    {
        // 1. Release with exhausted error budget (0% or negative) is frozen (363.2 & 363.4)
        try {
            $this->service->evaluateReleaseEligibility(
                releaseCode: 'REL-PROD-HAUL-V24',
                serviceCode: 'SVC-CORE-ERP',
                remainingBudgetPct: -0.05, // Budget exhausted!
                exceptionApproved: false
            );
            $this->fail('Expected exception for exhausted error budget release');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('SLO error budget exhausted (-0.05%): Release frozen', $e->getMessage());
        }

        // Verify frozen record recorded
        $frozen = DB::table('platform_slo_error_budget_releases')->where('release_code', 'REL-PROD-HAUL-V24')->first();
        $this->assertNotNull($frozen);
        $this->assertTrue((bool) $frozen->release_frozen);

        // 2. Release with exhausted budget but approved exception succeeds (363.2)
        $exceptionRelease = $this->service->evaluateReleaseEligibility(
            releaseCode: 'REL-PROD-HAUL-HOTFIX',
            serviceCode: 'SVC-CORE-ERP',
            remainingBudgetPct: -0.05,
            exceptionApproved: true // Exception approved by VP Engineering!
        );
        $this->assertFalse((bool) $exceptionRelease->release_frozen);
        $this->assertTrue((bool) $exceptionRelease->accountable_exception_approved);

        // 3. Normal release with healthy budget passes (363.2 & 363.4)
        $normal = $this->service->evaluateReleaseEligibility(
            releaseCode: 'REL-PROD-NORMAL-V25',
            serviceCode: 'SVC-CORE-ERP',
            remainingBudgetPct: 42.50,
            exceptionApproved: false
        );
        $this->assertFalse((bool) $normal->budget_exhausted);
        $this->assertFalse((bool) $normal->release_frozen);
    }

    public function test_platform_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerServiceReadiness('SVC-AUD', 'Audit Service', 'NON_CRITICAL', null, null);
        $this->service->evaluateReleaseEligibility('REL-AUD', 'SVC-AUD', 50.0, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: critical service without owner marked as passed
        DB::table('platform_service_oncall_registries')->insert([
            'service_code' => 'SVC-DEFECT-CRITICAL',
            'service_name' => 'Defect',
            'criticality_tier' => 'CRITICAL',
            'named_owner' => null, // Discrepancy!
            'oncall_rotation_id' => 'ROTA-1',
            'readiness_passed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
