<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Tests\TestCase;

class HospitalPhase104And105HttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_telemedicine_dashboard_authorized_and_unauthorized_access(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $unauthorized = User::factory()->create(['role' => 'agent']);

        // Doctor & Auditor can access GET dashboard
        $this->actingAs($doctor)->get(route('hospital.telemedicine.index'))->assertStatus(200);
        $this->actingAs($auditor)->get(route('hospital.telemedicine.index'))->assertStatus(200);

        // Unauthorized role receives 403
        $this->actingAs($unauthorized)->get(route('hospital.telemedicine.index'))->assertStatus(403);
    }

    public function test_prescription_safety_evaluation_post_endpoint(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $pharmacist = User::factory()->create(['role' => 'pharmacist']);
        $auditor = User::factory()->create(['role' => 'auditor']); // Prohibited on POST

        $patient = Patient::create([
            'mrn' => 'MRN-SEC-104',
            'name' => 'Safety Patient',
            'date_of_birth' => '1990-01-01',
            'blood_type' => 'A+',
            'encrypted_allergies' => json_encode(['PENICILLIN']),
            'passport_hash' => hash('sha256', 'PASSPORT:MRN-SEC-104'),
        ]);

        // Doctor evaluates prescription -> returns evaluation
        $response = $this->actingAs($doctor)->post(route('hospital.telemedicine.prescriptions.evaluate'), [
            'patient_id' => $patient->id,
            'drug_code' => 'AMOXICILLIN',
            'dosage_mg' => 500,
            'crcl' => 80,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHas('evaluation');

        // Pharmacist can also evaluate via JSON
        $jsonResp = $this->actingAs($pharmacist)->postJson(route('hospital.telemedicine.prescriptions.evaluate'), [
            'patient_id' => $patient->id,
            'drug_code' => 'METFORMIN',
            'dosage_mg' => 500,
            'crcl' => 20,
        ]);
        $jsonResp->assertStatus(200);
        $jsonResp->assertJsonPath('status', 'success');
        $this->assertFalse($jsonResp->json('data.is_safe'));

        // Auditor is rejected with 403 on write POST
        $this->actingAs($auditor)->post(route('hospital.telemedicine.prescriptions.evaluate'), [
            'patient_id' => $patient->id,
            'drug_code' => 'AMOXICILLIN',
            'dosage_mg' => 500,
        ])->assertStatus(403);
    }

    public function test_epharmacy_subscriptions_and_routing_dispatch(): void
    {
        $pharmacist = User::factory()->create(['role' => 'pharmacist']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $doctor = User::factory()->create(['role' => 'doctor']);

        // GET subscriptions dashboard
        $this->actingAs($pharmacist)->get(route('hospital.epharmacy.subscriptions.index'))->assertStatus(200);
        $this->actingAs($auditor)->get(route('hospital.epharmacy.subscriptions.index'))->assertStatus(200);

        // Branch & FEFO setup
        $branch = PharmacyBranch::create([
            'branch_code' => 'BR-JKT-01',
            'name' => 'Apotek Hospital Jakarta',
            'city' => 'Jakarta',
            'address' => 'Jl. Kesehatan 1',
            'phone' => '021-555123',
            'is_active' => true,
        ]);

        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'lot_number' => 'LOT-FEFO-01',
            'drug_name' => 'Metformin 500mg',
            'drug_code' => 'METFORMIN_500',
            'quantity' => 500,
            'expiry_date' => now()->addMonths(6),
            'is_quarantined' => false,
        ]);

        // Pharmacist dispatches routing
        $resp = $this->actingAs($pharmacist)->post(route('hospital.epharmacy.routing.dispatch'), [
            'city' => 'Jakarta',
            'drug_code' => 'METFORMIN_500',
            'quantity' => 100,
        ]);
        $resp->assertStatus(302);
        $resp->assertSessionHas('success');

        // Auditor cannot POST dispatch (403)
        $this->actingAs($auditor)->post(route('hospital.epharmacy.routing.dispatch'), [
            'city' => 'Jakarta',
            'drug_code' => 'METFORMIN_500',
            'quantity' => 100,
        ])->assertStatus(403);
    }

    public function test_laboratory_catalog_and_specimen_custody_scan(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $nurse = User::factory()->create(['role' => 'nurse']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $pharmacist = User::factory()->create(['role' => 'pharmacist']);

        // Catalog index
        $this->actingAs($doctor)->get(route('hospital.laboratory.catalog.index'))->assertStatus(200);
        $this->actingAs($pharmacist)->get(route('hospital.laboratory.catalog.index'))->assertStatus(200);
        $this->actingAs($auditor)->get(route('hospital.laboratory.catalog.index'))->assertStatus(200);

        $patient = Patient::create([
            'mrn' => 'MRN-LAB-SPEC-01',
            'name' => 'Lab Specimen Patient',
            'date_of_birth' => '1985-07-20',
            'blood_type' => 'B+',
            'passport_hash' => hash('sha256', 'PASSPORT:MRN-LAB-SPEC-01'),
        ]);

        $specimen = LabSpecimen::create([
            'specimen_barcode' => 'SPEC-TEST-105',
            'patient_id' => $patient->id,
            'sample_type' => 'BLOOD_EDTA',
            'collected_at' => now(),
            'custody_hash' => 'GENESIS',
            'status' => 'COLLECTED',
        ]);

        // Nurse scans custody hop
        $hopResp = $this->actingAs($nurse)->post(route('hospital.laboratory.specimens.custody'), [
            'specimen_id' => $specimen->id,
            'hop_number' => 1,
            'from_handler' => 'Nurse Phlebotomy',
            'to_handler' => 'Cold Courier Hub',
            'notes' => 'Temp verified 3.8C',
        ]);
        $hopResp->assertStatus(302);
        $this->assertDatabaseHas('hsp_lab_custody_transfers', [
            'specimen_id' => $specimen->id,
            'hop_number' => 1,
            'from_handler' => 'Nurse Phlebotomy',
        ]);

        // Auditor cannot POST custody scan (403)
        $this->actingAs($auditor)->post(route('hospital.laboratory.specimens.custody'), [
            'specimen_id' => $specimen->id,
            'hop_number' => 2,
            'from_handler' => 'Hub',
            'to_handler' => 'Analyzer',
        ])->assertStatus(403);
    }

    public function test_laboratory_outsourcing_and_dicom_pacs_endpoints(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $auditor = User::factory()->create(['role' => 'auditor']);
        $agent = User::factory()->create(['role' => 'agent']);

        // Outsourcing clearing index
        $this->actingAs($doctor)->get(route('hospital.laboratory.outsourcing.index'))->assertStatus(200);
        $this->actingAs($auditor)->get(route('hospital.laboratory.outsourcing.index'))->assertStatus(200);
        $this->actingAs($agent)->get(route('hospital.laboratory.outsourcing.index'))->assertStatus(403);

        // DICOM PACS index
        $this->actingAs($doctor)->get(route('hospital.imaging.pacs.index'))->assertStatus(200);
        $this->actingAs($auditor)->get(route('hospital.imaging.pacs.index'))->assertStatus(200);
        $this->actingAs($agent)->get(route('hospital.imaging.pacs.index'))->assertStatus(403);
    }
}
