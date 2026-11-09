<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\HospitalSurgicalSafeSurgeryChecklistService;
use PHPUnit\Framework\TestCase;

class HospitalSurgicalSafeSurgeryChecklistServiceTest extends TestCase
{
    private HospitalSurgicalSafeSurgeryChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HospitalSurgicalSafeSurgeryChecklistService();
    }

    public function test_sign_in_phase_passes_with_complete_checks(): void
    {
        $result = $this->service->verifySignInPhase(
            true, // Identity confirmed
            true, // Site marked
            true, // Anesthesia check
            true, // Pulse ox ok
            true, // Allergy assessed
            false // Normal airway
        );

        $this->assertSame(HospitalSurgicalSafeSurgeryChecklistService::STATUS_CHECKLIST_VERIFIED, $result['status']);
        $this->assertTrue($result['is_cleared_for_next_stage']);
        $this->assertEmpty($result['halt_reasons']);
    }

    public function test_sign_in_phase_halts_when_site_not_marked(): void
    {
        $result = $this->service->verifySignInPhase(
            true,
            false, // Site NOT marked!
            true,
            true,
            true,
            false
        );

        $this->assertSame(HospitalSurgicalSafeSurgeryChecklistService::STATUS_CHECKLIST_HALTED, $result['status']);
        $this->assertFalse($result['is_cleared_for_next_stage']);
        $this->assertStringContainsString('surgical site marking', $result['halt_reasons'][0]);
    }

    public function test_time_out_phase_requires_antibiotics_and_blood_standby(): void
    {
        $result = $this->service->verifyTimeOutPhase(
            true,
            true,
            true,  // Antibiotics given
            true,  // Imaging on screen
            1200   // Anticipated blood loss 1200 mL
        );

        $this->assertSame(HospitalSurgicalSafeSurgeryChecklistService::STATUS_CHECKLIST_VERIFIED, $result['status']);
        $this->assertTrue($result['requires_blood_transfusion_standby']);
    }

    public function test_sign_out_phase_detects_sponge_count_discrepancy(): void
    {
        $result = $this->service->verifySignOutPhase(
            20,
            19, // Hilang 1 kasa!
            15,
            15,
            45,
            45,
            true,
            'PACU_RECOVERY_ROOM'
        );

        $this->assertSame(HospitalSurgicalSafeSurgeryChecklistService::STATUS_CHECKLIST_HALTED, $result['status']);
        $this->assertFalse($result['is_cleared_for_patient_discharge']);
        $this->assertStringContainsString('kasa steril selisih', $result['halt_reasons'][0]);
    }
}
