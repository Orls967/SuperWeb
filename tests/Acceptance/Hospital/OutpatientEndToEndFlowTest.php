<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Modules\Hospital\Domain\Models\TariffVersion;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientEndToEndFlowTest extends HospitalAcceptanceHelper
{
    protected Polyclinic $poli;
    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poli = Polyclinic::firstOrCreate(['code' => 'POLI_UMUM'], ['name' => 'Poli Umum', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $this->doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-E2E-01'], [
            'user_id' => $doctorUser->id,
            'polyclinic_id' => $this->poli->id,
            'is_active' => true,
        ]);

        TariffVersion::firstOrCreate(['service_code' => 'SRV-DOC-SPECIALIST'], [
            'name' => 'Konsultasi Dokter Spesialis',
            'category' => 'OUTPATIENT_FEE',
            'amount_idr' => 150000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
    }

    public function test_alur_lengkap_rawat_jalan_tunai_dari_pendaftaran_hingga_laporan(): void
    {
        // 1. Role: hospital_registration mendaftarkan pasien tunai
        $registrationStaff = $this->createHospitalUser('hospital_registration');
        $patientUser = $this->createHospitalUser('patient');

        $regResponse = $this->actingAs($registrationStaff)->post(route('hospital.outpatient.registration.store'), [
            'nik' => '3171099999990001',
            'full_name' => 'Budi Sudarsono',
            'date_of_birth' => '1988-08-17',
            'blood_type' => 'O',
            'polyclinic_id' => $this->poli->id,
            'doctor_id' => $this->doctor->id,
            'payment_method' => 'CASH',
        ]);
        $regResponse->assertRedirect(route('hospital.outpatient.queue.index'));

        $queue = QueueTicket::where('polyclinic_id', $this->poli->id)->latest('id')->firstOrFail();
        $encounter = OutpatientEncounter::where('queue_id', $queue->id)->firstOrFail();
        $this->assertSame(1, $queue->queue_number);
        $this->assertSame('WAITING', $queue->status);
        $this->assertSame('REGISTERED', $encounter->status);

        // Pasien dihubungkan dengan akun portalnya
        $encounter->patient->update(['user_id' => $patientUser->id]);

        // 2. Role: nurse memanggil antrian dan mencatat triage vital sign
        $nurse = $this->createHospitalUser('nurse');
        $callResponse = $this->actingAs($nurse)->post(route('hospital.outpatient.queue.call', $queue));
        $callResponse->assertRedirect();
        $this->assertSame('CALLED', $queue->fresh()->status);
        $this->assertSame('CALLED', $encounter->fresh()->status);

        $triageResponse = $this->actingAs($nurse)->post(route('hospital.outpatient.encounters.triage', $encounter), [
            'systolic' => 120,
            'diastolic' => 80,
            'pulse' => 76,
            'respiratory' => 18,
            'temperature' => 36.6,
        ]);
        $triageResponse->assertRedirect();
        $this->assertSame(120, $encounter->fresh()->systolic_bp);

        // 3. Role: doctor memeriksa medis & memberikan resep
        $doctorUser = $this->doctor->user;
        $examineResponse = $this->actingAs($doctorUser)->post(route('hospital.outpatient.encounters.examine', $encounter), [
            'doctor_notes' => 'Pasien mengeluh pusing dan demam ringan.',
            'icd10_code' => 'R51',
        ]);
        $examineResponse->assertRedirect();
        $this->assertSame('EXAMINED', $encounter->fresh()->status);

        // Dokter meresepkan Paracetamol
        $prescribeResponse = $this->actingAs($doctorUser)->post(route('hospital.outpatient.encounters.prescribe', $encounter), [
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg Tab',
            'quantity' => 10,
            'dosage_instructions' => '3x1 tablet sesudah makan',
        ]);
        $prescribeResponse->assertRedirect();
        $prescription = OutpatientPrescription::where('encounter_id', $encounter->id)->firstOrFail();
        $this->assertSame('PRESCRIBED', $prescription->status);

        // 4. Role: pharmacist menyiapkan batch obat & menyerahkan (FEFO)
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi Utama', 'city' => 'Jakarta']);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg Tab',
            'lot_number' => 'LOT-E2E-01',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'unit_cost_idr' => 2000,
            'unit_sale_price_idr' => 3500,
            'is_quarantined' => false,
        ]);

        $dispenseResponse = $this->actingAs($pharmacist)->post(route('hospital.outpatient.pharmacy.dispense', $prescription));
        $dispenseResponse->assertRedirect();
        $this->assertSame('DISPENSED', $prescription->fresh()->status);

        // Buat invoice tagihan
        $billingAction = app(\Modules\Hospital\Application\Actions\GenerateOutpatientInvoiceAction::class);
        $invoice = $billingAction->execute($encounter->fresh());
        $this->assertSame(150000 + 20000, $invoice->total_amount_idr); // 150.000 dokter + 20.000 obat

        // 5. Role: cashier menerima pembayaran tunai (idempotent)
        $cashier = $this->createHospitalUser('cashier');
        $payResponse = $this->actingAs($cashier)->withHeaders([
            'Idempotency-Key' => 'E2E-PAY-BUDI-001',
        ])->post(route('hospital.outpatient.cashier.pay', $invoice), [
            'cash_received_idr' => 200000,
        ]);
        $payResponse->assertRedirect();
        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertSame('SETTLED', $encounter->fresh()->status);

        // 6. Role: patient memeriksa portal mandiri
        $portalResponse = $this->actingAs($patientUser)->get(route('hospital.outpatient.portal.index'));
        $portalResponse->assertOk();
        $portalResponse->assertSee($encounter->encounter_code);
        $portalResponse->assertSee($invoice->invoice_code);

        // 7. Role: hospital_finance & auditor melihat rekapitulasi harian
        $finance = $this->createHospitalUser('hospital_finance');
        $reportResponse = $this->actingAs($finance)->get(route('hospital.outpatient.reports.daily'));
        $reportResponse->assertOk();
        $reportResponse->assertSee('Poli Umum');
        $reportResponse->assertSee(number_format($invoice->total_amount_idr, 0, ',', '.'));
    }

    public function test_alur_lengkap_rawat_jalan_bpjs_klaim_inacbg_hingga_adjudikasi(): void
    {
        // 1. Pendaftaran pasien BPJS
        $registrationStaff = $this->createHospitalUser('hospital_registration');
        $regResponse = $this->actingAs($registrationStaff)->post(route('hospital.outpatient.registration.store'), [
            'nik' => '3171099999990002',
            'full_name' => 'Siti Rahmawati',
            'date_of_birth' => '1995-12-01',
            'blood_type' => 'A',
            'polyclinic_id' => $this->poli->id,
            'doctor_id' => $this->doctor->id,
            'payment_method' => 'BPJS',
            'bpjs_card_number' => '0001234567890',
        ]);
        $regResponse->assertRedirect();

        $queue = QueueTicket::where('polyclinic_id', $this->poli->id)->latest('id')->firstOrFail();
        $encounter = OutpatientEncounter::where('queue_id', $queue->id)->firstOrFail();
        $this->assertSame('BPJS', $encounter->payment_method);

        // 2. Pemeriksaan dokter
        $doctorUser = $this->doctor->user;
        $encounter->update(['status' => 'CALLED']); // Dipanggil
        $this->actingAs($doctorUser)->post(route('hospital.outpatient.encounters.examine', $encounter), [
            'doctor_notes' => 'Gastritis akut, evaluasi penjamin BPJS.',
            'icd10_code' => 'K29.7',
        ])->assertRedirect();

        // Tagihan RS terbentuk
        $billingAction = app(\Modules\Hospital\Application\Actions\GenerateOutpatientInvoiceAction::class);
        $invoice = $billingAction->execute($encounter->fresh());
        $this->assertSame(150000, $invoice->total_amount_idr);

        // 3. Finance mengajukan klaim tarif paket INA-CBG
        $finance = $this->createHospitalUser('hospital_finance');
        $claimResponse = $this->actingAs($finance)->post(route('hospital.outpatient.bpjs.claim', $invoice), [
            'ina_cbg_code' => 'Q-5-44-0',
            'package_tariff_idr' => 180000,
        ]);
        $claimResponse->assertRedirect();
        $this->assertSame('CLAIMED', $invoice->fresh()->status);

        $claim = \Modules\Hospital\Domain\Models\BpjsClaim::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('SUBMITTED', $claim->status);
        $this->assertSame(30000, $claim->variance_amount_idr); // 180.000 paket - 150.000 RS

        // 4. Finance menerima adjudikasi persetujuan klaim BPJS
        $adjudicateResponse = $this->actingAs($finance)->post(route('hospital.outpatient.bpjs.adjudicate', $claim), [
            'approved' => 'true',
        ]);
        $adjudicateResponse->assertRedirect();
        $this->assertSame('APPROVED', $claim->fresh()->status);
        $this->assertSame('SETTLED', $encounter->fresh()->status);
    }
}
