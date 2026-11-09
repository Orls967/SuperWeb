<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\HospitalRadioprotectionDosimetryService;
use PHPUnit\Framework\TestCase;

class HospitalRadioprotectionDosimetryServiceTest extends TestCase
{
    private HospitalRadioprotectionDosimetryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HospitalRadioprotectionDosimetryService();
    }

    public function test_normal_radiation_exposure_within_safe_limits(): void
    {
        // 1.2 mSv kuartal ini, kumulatif tahun berjalan 3.5 mSv (total 4.7 < 20 mSv)
        $result = $this->service->evaluateWorkerDosimetry(
            'RAD-TECH-001',
            'CT_SCAN',
            1.2,
            3.5,
            false
        );

        $this->assertSame(4.7, $result['total_cumulative_year_msv']);
        $this->assertSame(HospitalRadioprotectionDosimetryService::STATUS_NORMAL_SAFE, $result['status']);
        $this->assertFalse($result['is_investigation_required']);
        $this->assertFalse($result['is_immediate_rotation_mandated']);
        $this->assertSame(15.3, $result['remaining_annual_allowance_msv']);
    }

    public function test_elevated_quarterly_reading_triggers_investigation(): void
    {
        // 5.1 mSv dalam 1 kuartal (> 4.5 mSv)
        $result = $this->service->evaluateWorkerDosimetry(
            'PHYSICIST-002',
            'CATH_LAB_FLUOROSCOPY',
            5.1,
            6.0,
            false
        );

        $this->assertSame(11.1, $result['total_cumulative_year_msv']);
        $this->assertSame(HospitalRadioprotectionDosimetryService::STATUS_ELEVATED_INVESTIGATION, $result['status']);
        $this->assertTrue($result['is_investigation_required']);
        $this->assertFalse($result['is_immediate_rotation_mandated']);
    }

    public function test_pregnant_radiation_worker_limit_enforced_strictly(): void
    {
        // Pekerja hamil dengan total kumulatif 1.1 mSv (melewati 1.0 mSv)
        $result = $this->service->evaluateWorkerDosimetry(
            'NURSE-RAD-003',
            'NUCLEAR_MEDICINE',
            0.6,
            0.5,
            true // Hamil
        );

        $this->assertSame(1.1, $result['total_cumulative_year_msv']);
        $this->assertSame(HospitalRadioprotectionDosimetryService::STATUS_OVEREXPOSURE_ROTATION, $result['status']);
        $this->assertTrue($result['is_immediate_rotation_mandated']);
        $this->assertStringContainsString('PROTEKSI JANIN', $result['radiation_safety_officer_action']);
    }
}
