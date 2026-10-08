<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ModelIncidentSafetyCaseService;
use Tests\TestCase;

class ModelIncidentSafetyCaseTest extends TestCase
{
    use RefreshDatabase;

    protected ModelIncidentSafetyCaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModelIncidentSafetyCaseService::class);
    }

    public function test_high_impact_safety_case_launch_gate(): void
    {
        // 1. High-impact model without approved safety case is blocked from launch (356.2 & 356.4)
        try {
            $this->service->registerSafetyCase(
                caseCode: 'CASE-AUTONOMOUS-TRUCK-NAV',
                modelId: 'MODEL-NAV-TRUCK-V4',
                impactTier: 'HIGH_IMPACT',
                hasApprovedCase: false // No approved safety case!
            );
            $this->fail('Expected exception for unapproved high-impact safety case');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('High-impact model cannot launch without an approved safety case', $e->getMessage());
        }

        // 2. High-impact model with approved safety case launches (356.2 & 356.4)
        $case = $this->service->registerSafetyCase(
            caseCode: 'CASE-AUTONOMOUS-TRUCK-NAV-V5',
            modelId: 'MODEL-NAV-TRUCK-V5',
            impactTier: 'HIGH_IMPACT',
            hasApprovedCase: true
        );
        $this->assertTrue((bool) $case->has_approved_safety_case);
        $this->assertTrue((bool) $case->launch_permitted);

        // 3. Low-impact model launches directly (356.4)
        $lowCase = $this->service->registerSafetyCase(
            caseCode: 'CASE-RESTAURANT-MENU-SUGGEST',
            modelId: 'MODEL-MENU-REC-01',
            impactTier: 'LOW_IMPACT',
            hasApprovedCase: false
        );
        $this->assertTrue((bool) $lowCase->launch_permitted);
    }

    public function test_multi_domain_incident_war_room_activation_edge_case(): void
    {
        // 1. Single-domain incident does not activate cross-domain war room (356.1)
        $singleInc = $this->service->reportIncident(
            incidentCode: 'INC-MINE-DISPATCH-01',
            modelId: 'MODEL-MINE-DISPATCH',
            severity: 'HIGH',
            affectsMultipleDomains: false, // Single domain!
            containmentRollback: true
        );
        $this->assertFalse((bool) $singleInc->cross_domain_war_room_activated);
        $this->assertTrue((bool) $singleInc->containment_rollback_executed);

        // 2. High-impact multi-domain incident activates cross-domain war room (356.1 & 356.5 Edge Case)
        $multiInc = $this->service->reportIncident(
            incidentCode: 'INC-SUPPLY-CHAIN-PRICING-02',
            modelId: 'MODEL-GLOBAL-ERP-PRICING',
            severity: 'CRITICAL',
            affectsMultipleDomains: true, // Multiple domains affected!
            containmentRollback: true
        );
        $this->assertTrue((bool) $multiInc->cross_domain_war_room_activated);
        $this->assertTrue((bool) $multiInc->containment_rollback_executed);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerSafetyCase('CASE-AUD', 'M-AUD', 'HIGH_IMPACT', true);
        $this->service->reportIncident('INC-AUD', 'M-AUD', 'LOW', false, false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved high impact launched
        DB::table('ai_high_impact_safety_cases')->insert([
            'case_code' => 'CASE-DEFECT-UNAPPROVED',
            'model_identifier' => 'M-ROGUE',
            'impact_tier' => 'HIGH_IMPACT',
            'has_approved_safety_case' => false, // Discrepancy!
            'launch_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
