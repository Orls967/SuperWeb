<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Contracts\PatientReferralBooker;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Polyclinic;
use Tests\TestCase;

class PatientReferralBookerContractTest extends TestCase
{
    use RefreshDatabase;

    protected PatientReferralBooker $booker;

    protected Patient $patient;

    protected Doctor $doctor;

    protected PharmacyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->booker = app(PatientReferralBooker::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-K30-TEST-01',
            'name' => 'Budi Santoso K30',
            'date_of_birth' => '1988-04-12',
            'blood_type' => 'O_POSITIVE',
            'passport_hash' => hash('sha256', 'PASSPORT-K30-01'),
        ]);

        $user = User::factory()->create(['role' => 'doctor']);
        $poli = Polyclinic::create(['code' => 'POLI-INT', 'name' => 'Poli Penyakit Dalam']);

        $this->doctor = Doctor::create([
            'user_id' => $user->id,
            'polyclinic_id' => $poli->id,
            'doctor_code' => 'DOC-INT-01',
            'sip_number' => 'SIP-503/123/DISKES/2026',
            'specialization' => 'INTERNAL_MEDICINE',
            'is_active' => true,
        ]);

        $this->branch = PharmacyBranch::create([
            'branch_code' => 'BR-K30-01',
            'name' => 'Apotek Jaringan SuperWeb Thamrin',
            'type' => 'INTERNAL',
            'city' => 'Jakarta Pusat',
            'latitude' => -6.1950000,
            'longitude' => 106.8230000,
            'full_address' => 'Jl. MH Thamrin No. 10',
            'has_cold_chain_storage' => true,
            'is_active' => true,
        ]);

        // Sediakan stok obat FEFO di cabang
        PharmacyBranchLot::create([
            'branch_id' => $this->branch->id,
            'drug_code' => 'AMLODIPINE',
            'drug_name' => 'Amlodipine Besylate 10mg',
            'lot_number' => 'LOT-AMLO-2026-A1',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'is_quarantined' => false,
        ]);

        // Sediakan bed kamar rawat inap untuk pengujian admisi
        HospitalBed::create([
            'bed_code' => 'BED-VIP-K30',
            'ward_name' => 'Paviliun Melati',
            'room_number' => 'RM-101',
            'bed_class' => 'VIP',
            'rate_per_day_idr' => 1500000,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_k30_1_book_teleconsultation_with_triage(): void
    {
        $consult = $this->booker->bookTeleconsultation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'channel_type' => 'VIDEO',
            'chief_complaint' => 'Kontrol tekanan darah rutin dan evaluasi resep',
        ]);

        $this->assertNotNull($consult->id);
        $this->assertStringStartsWith('TC-', $consult->consult_code);
        $this->assertSame('GREEN', $consult->triage_category);
        $this->assertSame('SCHEDULED', $consult->status);
    }

    public function test_k30_2_issue_electronic_prescription_with_digital_signature(): void
    {
        $consult = $this->booker->bookTeleconsultation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'chief_complaint' => 'Keluhan hipertensi kronis',
        ]);

        $order = $this->booker->issueElectronicPrescription([
            'tele_consult_id' => $consult->id,
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'AMLODIPINE',
            'drug_name' => 'Amlodipine Besylate 10mg',
            'quantity' => 30,
            'total_price_idr' => 150_000,
            'drug_classification' => 'REGULAR',
            'doctor_sip_number' => $this->doctor->sip_number,
        ]);

        $this->assertNotNull($order->id);
        $this->assertStringStartsWith('ORD-EP-', $order->order_code);
        $this->assertNotEmpty($order->doctor_signature_hash);
        $this->assertSame('CONFIRMED', $order->status);
    }

    public function test_k30_3_route_prescription_to_optimal_geospatial_branch(): void
    {
        // Pasien di koordinat Bundaran HI (-6.1955, 106.8235)
        $selectedBranch = $this->booker->routePrescriptionToPharmacy(
            drugCode: 'AMLODIPINE',
            quantity: 30,
            patientLatitude: -6.1955,
            patientLongitude: 106.8235,
            maxRadiusKm: 10.0
        );

        $this->assertSame($this->branch->id, $selectedBranch->id);
    }

    public function test_k30_4_dispatch_medicine_delivery_with_fefo_depletion(): void
    {
        $consult = $this->booker->bookTeleconsultation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'chief_complaint' => 'Sakit kepala dan tensi naik',
        ]);

        $order = $this->booker->issueElectronicPrescription([
            'tele_consult_id' => $consult->id,
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'AMLODIPINE',
            'drug_name' => 'Amlodipine Besylate 10mg',
            'quantity' => 30,
            'total_price_idr' => 150_000,
            'doctor_sip_number' => $this->doctor->sip_number,
        ]);

        $delivery = $this->booker->dispatchMedicineDelivery([
            'order_id' => $order->id,
            'courier_service' => 'AMBULANCE_EXPRESS',
            'recipient_name' => 'Budi Santoso',
            'recipient_phone' => '081234567890',
            'destination_address' => 'Jl. Kebon Sirih No. 5, Jakarta Pusat',
            'requires_cold_chain' => false,
        ]);

        $this->assertNotNull($delivery->id);
        $this->assertSame('IN_TRANSIT', $delivery->delivery_status);

        // Pastikan lot FEFO terpotong (100 - 30 = 70)
        $lot = PharmacyBranchLot::where('branch_id', $this->branch->id)->where('drug_code', 'AMLODIPINE')->first();
        $this->assertSame(70, $lot->quantity);
    }

    public function test_k30_5_bridge_bpjs_sisrute_referral(): void
    {
        $referral = $this->booker->bridgeBpjsReferral([
            'patient_id' => $this->patient->id,
            'referral_direction' => 'OUTBOUND',
            'referral_type' => 'VERTICAL_UP',
            'origin_faskes_code' => '0123R001',
            'origin_faskes_name' => 'RS Umum Daerah Tipe C',
            'destination_faskes_code' => '0123R999',
            'destination_faskes_name' => 'RS Jantung Harapan Kita Tipe A',
            'diagnosis_icd10' => 'I25.1',
            'clinical_summary' => 'Angina pektoris stabil membutuhkan kateterisasi jantung PCI elektif.',
            'target_polyclinic_or_ward' => 'POLI_JANTUNG',
        ]);

        $this->assertNotNull($referral->id);
        $this->assertStringStartsWith('SISRUTE-', $referral->sisrute_reference_number);
        $this->assertSame('REQUESTED', $referral->approval_status);
    }

    public function test_k30_6_book_inpatient_from_telemedicine_referral(): void
    {
        $consult = $this->booker->bookTeleconsultation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'chief_complaint' => 'Sesak napas dan butuh perawatan intensif',
        ]);

        $inpatientBooking = $this->booker->bookInpatientFromReferral(
            teleConsultId: $consult->id,
            targetWardClass: 'VIP',
            primaryDiagnosisIcd10: 'J18.9'
        );

        $this->assertSame('CONFIRMED', $inpatientBooking['status']);
        $this->assertSame('BED-VIP-K30', $inpatientBooking['bed_code']);
        $this->assertSame(10_000_000, $inpatientBooking['required_escrow_deposit_idr']);

        // Pastikan bed fisik menjadi OCCUPIED
        $bed = HospitalBed::where('bed_code', 'BED-VIP-K30')->first();
        $this->assertSame('OCCUPIED', $bed->status);
    }
}
