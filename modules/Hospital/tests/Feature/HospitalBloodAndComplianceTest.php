<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\HospitalBloodAndComplianceService;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\MedicalEquipment;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;
use Tests\TestCase;

class HospitalBloodAndComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalBloodAndComplianceService $service;

    protected Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalBloodAndComplianceService::class);

        $patient = Patient::create([
            'mrn' => 'MRN-BLOOD-01',
            'name' => 'Surgery Blood Recipient',
            'date_of_birth' => '1984-07-14',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT-BLOOD-01'),
        ]);

        $this->encounter = Encounter::create([
            'encounter_code' => 'ENC-BLOOD-01',
            'patient_id' => $patient->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'ADMITTED',
        ]);
    }

    public function test_109_1_and_109_6_a_expired_blood_bag_cannot_be_reserved(): void
    {
        $expiredBag = $this->service->registerBloodBag(
            bloodType: 'O+',
            componentType: 'PACKED_RED_CELLS',
            expiryDate: date('Y-m-d', strtotime('-5 days')),
            donorScreeningHash: hash('sha256', 'DONOR-SCREEN-01')
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is expired');

        $this->service->reserveBloodBag($expiredBag, $this->encounter);
    }

    public function test_109_1_and_109_6_b_blood_bag_recall_traces_recipient_instantly(): void
    {
        $validBag = $this->service->registerBloodBag(
            bloodType: 'O+',
            componentType: 'PACKED_RED_CELLS',
            expiryDate: date('Y-m-d', strtotime('+20 days')),
            donorScreeningHash: hash('sha256', 'DONOR-SCREEN-02')
        );

        $reserved = $this->service->reserveBloodBag($validBag, $this->encounter);
        $this->assertEquals('RESERVED', $reserved->status);

        // Emergency recall traces patient encounter
        $recallInfo = $this->service->recallBloodBag($validBag);
        $this->assertEquals('RECALLED', $recallInfo['status']);
        $this->assertEquals($this->encounter->id, $recallInfo['affected_encounter_id']);
        $this->assertEquals('EMERGENCY_RECIPIENT_TRACED', $recallInfo['urgent_clinical_alert']);
    }

    public function test_109_3_and_109_6_c_expired_calibration_blocks_clinical_use(): void
    {
        $equipment = MedicalEquipment::create([
            'equipment_code' => 'VENT-ICU-08',
            'name' => 'High-Frequency ICU Ventilator',
            'category' => 'VENTILATOR',
            'calibration_expires_at' => date('Y-m-d', strtotime('-2 days')),
            'calibration_certificate_hash' => hash('sha256', 'CALIB-CERT-2025'),
            'status' => 'CERTIFIED',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('calibration expired');

        $this->service->verifyEquipmentUsability($equipment);
    }

    public function test_109_5_and_109_6_d_gapless_waste_manifest_and_valid_custody(): void
    {
        $manifest1 = $this->service->dispatchMedicalWaste('SHARPS', 45.5, 'PARTY-WASTE-VENDOR-01');
        $manifest2 = $this->service->dispatchMedicalWaste('INFECTIOUS', 120.0, 'PARTY-WASTE-VENDOR-01');

        $this->assertEquals('HAZ-MED-000001', $manifest1->manifest_number);
        $this->assertEquals('HAZ-MED-000002', $manifest2->manifest_number);
        $this->assertNotEmpty($manifest1->custody_hash);
        $this->assertEquals('DISPATCHED', $manifest1->status);
    }
}
