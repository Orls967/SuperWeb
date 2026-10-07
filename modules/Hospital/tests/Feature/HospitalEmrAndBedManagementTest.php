<?php

namespace Modules\Hospital\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\HospitalEmrAndBedService;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\HospitalOrder;
use Tests\TestCase;

class HospitalEmrAndBedManagementTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalEmrAndBedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalEmrAndBedService::class);
    }

    public function test_87_2_patient_registration_with_hash_chained_health_passport(): void
    {
        $patient = $this->service->registerPatientWithPassport(
            mrn: 'MRN-2026-0001',
            name: 'John Doe',
            dob: Carbon::parse('1988-05-12'),
            bloodType: 'O+',
            allergies: ['PENICILLIN', 'PEANUTS'],
            chronicDiagnoses: ['HYPERTENSION_STAGE_1']
        );

        $this->assertEquals('MRN-2026-0001', $patient->mrn);
        $this->assertNotNull($patient->passport_hash);
        $this->assertCount(2, $patient->encrypted_allergies);
    }

    public function test_87_3_real_time_bed_allocation_and_anti_overlap_guardrail(): void
    {
        $patient1 = $this->service->registerPatientWithPassport(
            mrn: 'MRN-01',
            name: 'Patient One',
            dob: Carbon::parse('1990-01-01'),
            bloodType: 'A+',
            allergies: [],
            chronicDiagnoses: []
        );

        $encounter1 = Encounter::create([
            'encounter_code' => 'ENC-01',
            'patient_id' => $patient1->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'ADMITTED',
        ]);

        $bed = HospitalBed::create([
            'bed_code' => 'BED-ICU-01',
            'ward_name' => 'Intensive Care Unit',
            'room_number' => 'ICU-1',
            'bed_class' => 'ICU',
            'rate_per_day_idr' => 2_500_000,
            'status' => 'AVAILABLE',
        ]);

        // Allocate bed to patient 1
        $allocated = $this->service->allocateBed($bed, $encounter1);
        $this->assertEquals('OCCUPIED', $allocated->status);
        $this->assertEquals($encounter1->id, $allocated->current_encounter_id);

        // Attempting to allocate occupied bed to another patient fails
        $patient2 = $this->service->registerPatientWithPassport(
            mrn: 'MRN-02',
            name: 'Patient Two',
            dob: Carbon::parse('1992-02-02'),
            bloodType: 'B+',
            allergies: [],
            chronicDiagnoses: []
        );

        $encounter2 = Encounter::create([
            'encounter_code' => 'ENC-02',
            'patient_id' => $patient2->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'ADMITTED',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->allocateBed($allocated, $encounter2);
    }

    public function test_87_4_and_87_5_clinical_pathway_alerts_and_icu_code_blue_telemetry(): void
    {
        $patient = $this->service->registerPatientWithPassport(
            mrn: 'MRN-ICU-99',
            name: 'Critical Patient',
            dob: Carbon::parse('1975-03-15'),
            bloodType: 'AB+',
            allergies: [],
            chronicDiagnoses: ['SEPSIS']
        );

        $encounter = Encounter::create([
            'encounter_code' => 'ENC-CRIT-99',
            'patient_id' => $patient->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now()->subHours(4),
            'status' => 'ADMITTED',
        ]);

        // Create overdue doctor order
        HospitalOrder::create([
            'order_code' => 'ORD-MED-01',
            'encounter_id' => $encounter->id,
            'order_type' => 'MEDICATION',
            'description' => 'IV Antibiotics Meropenem 1g',
            'scheduled_at' => now()->subHours(2), // 2 hours overdue
            'status' => 'PENDING',
        ]);

        // Delay check triggers alert exactly once
        $alerts = $this->service->checkClinicalPathwayDelays($encounter, now());
        $this->assertCount(1, $alerts);

        // Re-check does not re-alert (delay_alert_sent = true)
        $secondAlerts = $this->service->checkClinicalPathwayDelays($encounter, now());
        $this->assertCount(0, $secondAlerts);

        // IoT Telemetry: SpO2 critical drop (82% < 85%) -> Code Blue triggered
        $vitals = $this->service->ingestVitalsTelemetry(
            encounter: $encounter,
            spo2: 82.0,
            heartRate: 142,
            tempC: 38.8,
            recordedAt: now()
        );

        $this->assertTrue($vitals->code_blue_triggered);
        $this->assertNotNull($vitals->proof_hash);
    }
}
