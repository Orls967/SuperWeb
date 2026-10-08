<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SafetyCertifiedPermitAccessService;
use Tests\TestCase;

class SafetyCertifiedPermitAccessTest extends TestCase
{
    use RefreshDatabase;

    protected SafetyCertifiedPermitAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SafetyCertifiedPermitAccessService::class);
    }

    public function test_credential_to_access_bridge_and_expired_cert_revocation(): void
    {
        // 1. Fully certified, non-expired worker with induction & signoff gets access (323.1 & 323.4)
        $permit = $this->service->evaluatePermitAccess(
            permitCode: 'PERMIT-SMELTER-FURNACE-01',
            workerId: 'WORKER_ADI',
            siteCode: 'SITE_SMELTER_MOROWALI',
            areaOrEquipment: 'FURNACE_ZONE_HIGH_TEMP',
            validCert: true,
            certExpired: false,
            siteInductionCompleted: true,
            supervisorSignedOff: true
        );
        $this->assertTrue((bool) $permit->access_granted);

        // 2. Expired certificate strictly blocks access (323.1 & 323.4)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Safety access violation: Worker is not certified, credentials expired');
        $this->service->evaluatePermitAccess(
            permitCode: 'PERMIT-EXPIRED-CERT',
            workerId: 'WORKER_BAYU',
            siteCode: 'SITE_SMELTER_MOROWALI',
            areaOrEquipment: 'FURNACE_ZONE_HIGH_TEMP',
            validCert: true,
            certExpired: true, // Expired cert!
            siteInductionCompleted: true,
            supervisorSignedOff: true
        );
    }

    public function test_emergency_permit_path_with_post_review(): void
    {
        // 1. Emergency without post review fails
        try {
            $this->service->evaluatePermitAccess(
                permitCode: 'PERMIT-EMERG-FAIL',
                workerId: 'WORKER_EMERG_01',
                siteCode: 'SITE_MINE_PIT',
                areaOrEquipment: 'PUMP_STATION',
                validCert: false,
                certExpired: false,
                siteInductionCompleted: false,
                supervisorSignedOff: true,
                isEmergency: true,
                emergencyPostReview: false
            );
            $this->fail('Expected exception for unreviewed emergency permit');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Safety access violation', $e->getMessage());
        }

        // 2. Emergency with supervisor signoff & documented post review succeeds (323.5 Edge Case)
        $emergencyPermit = $this->service->evaluatePermitAccess(
            permitCode: 'PERMIT-EMERG-OK',
            workerId: 'WORKER_EMERG_01',
            siteCode: 'SITE_MINE_PIT',
            areaOrEquipment: 'PUMP_STATION',
            validCert: false,
            certExpired: false,
            siteInductionCompleted: false,
            supervisorSignedOff: true,
            isEmergency: true,
            emergencyPostReview: true
        );
        $this->assertTrue((bool) $emergencyPermit->access_granted);
        $this->assertTrue((bool) $emergencyPermit->is_emergency_permit);
    }

    public function test_stop_work_authority_protection(): void
    {
        // Stop-work authority recorded with anti-retaliation protection (323.3 & 323.4)
        $incident = $this->service->invokeStopWorkAuthority(
            incidentCode: 'STOP-WORK-GAS-LEAK-01',
            workerId: 'WORKER_ADI',
            siteCode: 'SITE_SMELTER_MOROWALI',
            hazardDescription: 'Uncontained gas hiss detected near valve manifold'
        );
        $this->assertFalse((bool) $incident->restart_authorized);
        $this->assertTrue((bool) $incident->retaliatory_action_prevented);
    }

    public function test_hcm_safety_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->evaluatePermitAccess('P-AUD', 'W1', 'S1', 'AREA1', true, false, true, true);
        $this->service->invokeStopWorkAuthority('INC-AUD', 'W1', 'S1', 'HAZARD');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: access granted with expired cert
        DB::table('safety_certified_access_permits')->insert([
            'permit_code' => 'P-DEFECT-EXPIRED-ACCESS',
            'worker_id' => 'W_ROGUE',
            'site_code' => 'S1',
            'area_or_equipment' => 'HAZARD_ZONE',
            'has_valid_certification' => true,
            'is_certification_expired' => true, // Discrepancy!
            'site_induction_completed' => true,
            'supervisor_signed_off' => true,
            'is_emergency_permit' => false,
            'emergency_post_review_completed' => false,
            'access_granted' => true, // Improper access!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
