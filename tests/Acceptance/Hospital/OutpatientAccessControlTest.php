<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use App\Models\User;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientAccessControlTest extends HospitalAcceptanceHelper
{
    protected function createPatientWithUser(): array
    {
        $user = $this->createHospitalUser('patient');
        $patient = Patient::create([
            'mrn' => 'MRN-'.uniqid(),
            'name' => $user->name,
            'date_of_birth' => '1990-01-01',
            'blood_type' => 'O',
            'user_id' => $user->id,
            'passport_hash' => hash('sha256', 'p'),
        ]);

        return [$user, $patient];
    }

    protected function createEncounterWithInvoice(Patient $patient): array
    {
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $queue = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => now()->toDateString(), 'queue_number' => 1, 'queue_code' => 'T-01', 'status' => 'SERVED']);

        $encounter = OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => 'INVOICED',
            'encrypted_icd10_code' => 'K30',
            'encrypted_doctor_notes' => 'Catatan klinis rahasia pasien',
        ]);

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 150000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        return [$encounter, $invoice];
    }

    public function test_patient_a_dapat_melihat_kunjungan_dan_tagihan_sendiri(): void
    {
        [$userA, $patientA] = $this->createPatientWithUser();
        [$encounterA, $invoiceA] = $this->createEncounterWithInvoice($patientA);

        $response = $this->actingAs($userA)->get(route('hospital.outpatient.portal.index'));

        $response->assertOk();
        $response->assertSee($encounterA->encounter_code);
        $response->assertSee($invoiceA->invoice_code);
    }

    public function test_patient_a_tidak_bisa_melihat_kunjungan_atau_tagihan_patient_b(): void
    {
        [$userA, $patientA] = $this->createPatientWithUser();
        [$userB, $patientB] = $this->createPatientWithUser();

        [$encounterB, $invoiceB] = $this->createEncounterWithInvoice($patientB);

        // Pasien A mencoba mengakses detail kunjungan Pasien B
        $response = $this->actingAs($userA)->get(route('hospital.outpatient.encounters.show', $encounterB));
        $this->assertTrue(in_array($response->status(), [403, 404]));

        // Pasien A mencoba mengakses detail tagihan Pasien B
        $invResponse = $this->actingAs($userA)->get(route('hospital.outpatient.invoices.show', $invoiceB));
        $this->assertTrue(in_array($invResponse->status(), [403, 404]));
    }

    public function test_cashier_tidak_bisa_melihat_diagnosis_dan_catatan_medis(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        [$userPatient, $patient] = $this->createPatientWithUser();
        [$encounter, $invoice] = $this->createEncounterWithInvoice($patient);

        // Kasir mengakses halaman kasir tagihan
        $response = $this->actingAs($cashier)->get(route('hospital.outpatient.cashier.index'));
        $response->assertOk();

        // Diagnosis medis dan catatan dokter tidak boleh tampil pada antarmuka kasir
        $response->assertDontSee('Catatan klinis rahasia pasien');
        $response->assertDontSee('K30');
    }

    public function test_auditor_hanya_baca_saja_dan_ditolak_saat_post_mutasi(): void
    {
        $auditor = $this->createHospitalUser('auditor');
        [$userPatient, $patient] = $this->createPatientWithUser();
        [$encounter, $invoice] = $this->createEncounterWithInvoice($patient);

        // Auditor membaca laporan -> OK (200)
        $getRep = $this->actingAs($auditor)->get(route('hospital.outpatient.reports.daily'));
        $getRep->assertOk();

        // Auditor mencoba melakukan pembayaran -> FORBIDDEN (403)
        $postPay = $this->actingAs($auditor)->post(route('hospital.outpatient.cashier.pay', $invoice), [
            'cash_received_idr' => 150000,
        ], ['Idempotency-Key' => 'AUDIT-IDEMP-01']);
        $postPay->assertForbidden();

        // Auditor mencoba mendaftarkan pasien -> FORBIDDEN (403)
        $postReg = $this->actingAs($auditor)->post(route('hospital.outpatient.registration.store'), [
            'nik' => '3171000000000099',
        ]);
        $postReg->assertForbidden();
    }

    public function test_pengguna_belum_login_dialihkan_ke_login(): void
    {
        $response = $this->get(route('hospital.outpatient.registration.index'));
        $response->assertRedirect(route('login'));
    }
}
