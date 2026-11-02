<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Application\Actions\AdjudicateBpjsClaimAction;
use Modules\Hospital\Application\Actions\CreateOutpatientPrescriptionAction;
use Modules\Hospital\Application\Actions\DispensePrescriptionFefoAction;
use Modules\Hospital\Application\Actions\ExaminePatientAndDiagnoseAction;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;
use Modules\Hospital\Application\Actions\GenerateOutpatientInvoiceAction;
use Modules\Hospital\Application\Actions\ProcessCashPaymentAction;
use Modules\Hospital\Application\Actions\RecordVitalSignsAction;
use Modules\Hospital\Application\Actions\RegisterPatientAndIssueQueueAction;
use Modules\Hospital\Application\Actions\SubmitBpjsClaimAction;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Application\DTOs\DoctorExaminationDTO;
use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Application\DTOs\VitalSignsDTO;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientDispensing;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Modules\Hospital\Domain\Models\TariffVersion;
use Modules\Hospital\Ledger\HospitalTransactionTypes;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientRawatJalanAcceptanceTest extends HospitalAcceptanceHelper
{
    // ==================== OutpatientAccessControlTest.php ====================
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

    // ==================== OutpatientBillingAndCashierTest.php ====================
    protected function createDispensedEncounterFixture(): OutpatientEncounter
    {
        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_INT'], ['name' => 'Poli Dalam', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-01'], [
            'user_id' => $doctorUser->id,
            'polyclinic_id' => $poli->id,
            'is_active' => true,
        ]);
        $patient = Patient::create(['mrn' => 'MRN-'.uniqid(), 'name' => 'Pasien Billing', 'date_of_birth' => '1985-05-12', 'blood_type' => 'O', 'passport_hash' => hash('sha256', 'p')]);
        $queue = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => now()->toDateString(), 'queue_number' => (int) (QueueTicket::count() + 1), 'queue_code' => 'T-'.uniqid(), 'status' => 'SERVED']);

        $encounter = OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => 'DISPENSED',
            'encrypted_icd10_code' => 'K30',
        ]);

        OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $doctor->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 10,
            'dosage_instructions' => '3x1',
            'status' => 'DISPENSED',
            'unit_price_idr' => 2000,
            'subtotal_idr' => 20000,
        ]);

        return $encounter;
    }

    public function test_tagihan_dihitung_dari_tarif_dokter_versi_aktif_dan_biaya_obat(): void
    {
        TariffVersion::create([
            'service_code' => 'SRV-DOC-SPECIALIST',
            'name' => 'Jasa Konsultasi Spesialis',
            'category' => 'DOCTOR_FEE',
            'amount_idr' => 150000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $encounter = $this->createDispensedEncounterFixture();

        $action = app(GenerateOutpatientInvoiceAction::class);
        $invoice = $action->execute($encounter);

        $this->assertSame(150000, $invoice->doctor_fee_idr);
        $this->assertSame(20000, $invoice->medication_fee_idr);
        $this->assertSame(170000, $invoice->total_amount_idr);
        $this->assertSame('PENDING', $invoice->status);
        $this->assertSame('INVOICED', $encounter->fresh()->status);
    }

    public function test_pembayaran_tunai_memposting_debit_kas_dan_kredit_piutang_pasien(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $encounter = $this->createDispensedEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        $action = app(ProcessCashPaymentAction::class);
        $dto = new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: 200000,
            idempotencyKey: 'IDEMP-TEST-PAY-001',
            cashierUserId: $cashier->id,
        );

        $payment = $action->execute($dto);

        $this->assertSame(170000, $payment->amount_idr);
        $this->assertSame(200000, $payment->cash_received_idr);
        $this->assertSame(30000, $payment->change_given_idr);
        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertSame('SETTLED', $encounter->fresh()->status);

        // Verifikasi mutasi debit kas dan kredit piutang pasien di ledger
        $this->assertLedgerPosted('hsp:cash:IDR', 170000, 'DEBIT');
        $this->assertLedgerPosted('hsp:patient_receivable:IDR', 170000, 'CREDIT');
    }

    public function test_pembayaran_tanpa_header_idempotency_key_ditolak_422(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $encounter = $this->createDispensedEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 100000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 100000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        // Request HTTP tanpa header Idempotency-Key
        $response = $this->actingAs($cashier)->postJson(route('hospital.outpatient.cashier.pay', $invoice), [
            'cash_received_idr' => 100000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_pembayaran_dua_kali_dengan_idempotency_key_sama_hanya_satu_posting(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $encounter = $this->createDispensedEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 150000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        $key = 'IDEMP-REPLAY-KEY-123';
        $action = app(ProcessCashPaymentAction::class);
        $dto = new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: 150000,
            idempotencyKey: $key,
            cashierUserId: $cashier->id,
        );

        $res1 = $action->execute($dto);
        $res2 = $action->execute($dto); // Panggilan kedua dengan key yang sama

        $this->assertSame($res1->id, $res2->id);

        // Ledger hanya memuat tepat satu posting
        $entriesCount = DB::table('bank_ledger_transactions')
            ->where('idempotency_key', 'hsp:pay:'.$key)
            ->count();
        $this->assertSame(1, $entriesCount);
    }

    public function test_dua_kunjungan_berbeda_dengan_nominal_sama_tetap_diposting_terpisah(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $enc1 = $this->createDispensedEncounterFixture();
        $enc2 = $this->createDispensedEncounterFixture();

        $inv1 = OutpatientInvoice::create([
            'invoice_code' => 'INV-A-1',
            'encounter_id' => $enc1->id,
            'doctor_fee_idr' => 100000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 100000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        $inv2 = OutpatientInvoice::create([
            'invoice_code' => 'INV-B-2',
            'encounter_id' => $enc2->id,
            'doctor_fee_idr' => 100000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 100000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        $action = app(ProcessCashPaymentAction::class);
        $p1 = $action->execute(new CashPaymentDTO(invoiceId: $inv1->id, cashReceivedIdr: 100000, idempotencyKey: 'IDEMP-DISTINCT-1', cashierUserId: $cashier->id));
        $p2 = $action->execute(new CashPaymentDTO(invoiceId: $inv2->id, cashReceivedIdr: 100000, idempotencyKey: 'IDEMP-DISTINCT-2', cashierUserId: $cashier->id));

        $this->assertNotSame($p1->id, $p2->id);
        $cashAcc = DB::table('bank_ledger_accounts')->where('code', 'hsp:cash:IDR')->firstOrFail();
        $totalDebitCash = DB::table('bank_ledger_entries')->where('account_id', $cashAcc->id)->sum('amount');
        $this->assertSame(-200000, (int) $totalDebitCash);
    }

    public function test_pembayaran_kurang_bayar_ditolak(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $encounter = $this->createDispensedEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 150000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        $action = app(ProcessCashPaymentAction::class);
        $dto = new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: 100000, // Kurang 50.000
            idempotencyKey: 'IDEMP-SHORT-PAY',
            cashierUserId: $cashier->id,
        );

        $this->expectException(\DomainException::class);
        $action->execute($dto);
    }

    public function test_pembayaran_lebih_bayar_menghasilkan_kembalian_yang_tepat(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $encounter = $this->createDispensedEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 125000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 125000,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        $action = app(ProcessCashPaymentAction::class);
        $dto = new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: 150000,
            idempotencyKey: 'IDEMP-EXACT-CHANGE',
            cashierUserId: $cashier->id,
        );

        $payment = $action->execute($dto);
        $this->assertSame(25000, $payment->change_given_idr);
    }

    public function test_tagihan_yang_sudah_lunas_tidak_dapat_dibayar_ulang(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $encounter = $this->createDispensedEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 100000,
            'medication_fee_idr' => 0,
            'total_amount_idr' => 100000,
            'payment_method' => 'CASH',
            'status' => 'PAID', // Sudah lunas
        ]);

        $action = app(ProcessCashPaymentAction::class);
        $dto = new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: 100000,
            idempotencyKey: 'IDEMP-ALREADY-PAID',
            cashierUserId: $cashier->id,
        );

        $this->expectException(\DomainException::class);
        $action->execute($dto);
    }

    // ==================== OutpatientBpjsClaimTest.php ====================
    protected function createBpjsEncounterFixture(): OutpatientEncounter
    {
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Penyakit Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $patient = Patient::create(['mrn' => 'MRN-'.uniqid(), 'name' => 'Pasien BPJS', 'date_of_birth' => '1987-03-15', 'blood_type' => 'B', 'passport_hash' => hash('sha256', 'p')]);
        $queue = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => now()->toDateString(), 'queue_number' => 1, 'queue_code' => 'T-01', 'status' => 'SERVED']);

        return OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'BPJS',
            'bpjs_card_number' => '0001234567890',
            'status' => 'DISPENSED',
            'encrypted_icd10_code' => 'K30', // Dispepsia
        ]);
    }

    public function test_pendaftaran_pasien_jalur_bpjs_memvalidasi_nomor_kartu_jkn(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        // Pendaftaran jalur BPJS tanpa nomor kartu JKN harus ditolak
        $dtoWithoutCard = new RegisterPatientDTO(
            nik: '3171000000000099',
            fullName: 'BPJS Tanpa Kartu',
            dateOfBirth: Carbon::parse('1990-01-01'),
            bloodType: 'O',
            polyclinicId: $poli->id,
            doctorId: $doc->id,
            paymentMethod: 'BPJS',
            bpjsCardNumber: null, // Kosong
        );

        $this->expectException(\InvalidArgumentException::class);
        $action->execute($dtoWithoutCard, $regStaff);
    }

    public function test_tagihan_pasien_bpjs_menghasilkan_klaim_tarif_paket_inacbg_ke_piutang_bpjs(): void
    {
        $encounter = $this->createBpjsEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-BPJS-01',
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000,
            'payment_method' => 'BPJS',
            'status' => 'PENDING',
        ]);

        $action = app(SubmitBpjsClaimAction::class);
        // Paket INA-CBG untuk Rawat Jalan K30 (Dispepsia Ringan): Tarif Paket Rp 180.000
        $claim = $action->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);

        $this->assertInstanceOf(BpjsClaim::class, $claim);
        $this->assertSame(180000, $claim->ina_cbg_tariff_idr);
        $this->assertSame('SUBMITTED', $claim->status);
        $this->assertSame('CLAIMED', $invoice->fresh()->status);
        $this->assertSame('CLAIMED', $encounter->fresh()->status);

        // Verifikasi mutasi debit pada Piutang BPJS sebesar nilai paket INA-CBG Rp 180.000
        $this->assertLedgerPosted('hsp:bpjs_receivable:IDR', 180000, 'DEBIT');
    }

    public function test_selisih_tarif_rs_vs_paket_inacbg_diakui_pada_akun_selisih_secara_seimbang(): void
    {
        $encounter = $this->createBpjsEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-BPJS-02',
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000, // Tarif RS = 170.000
            'payment_method' => 'BPJS',
            'status' => 'PENDING',
        ]);

        $action = app(SubmitBpjsClaimAction::class);
        // Paket INA-CBG Rp 180.000 -> Surplus selisih Rp 10.000
        $claim = $action->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);

        $this->assertSame(10000, $claim->variance_amount_idr);

        // Kredit pendapatan selisih tarif Rp 10.000
        $this->assertLedgerPosted('hsp:bpjs_claim_variance:IDR', 10000, 'CREDIT');

        // Total debit dan total kredit posting klaim harus seimbang
        $tx = DB::table('bank_ledger_transactions')
            ->where('reference_type', 'HSP_BPJS_CLAIM')
            ->where('reference_id', (string) $claim->id)
            ->firstOrFail();

        $sum = DB::table('bank_ledger_entries')->where('transaction_id', $tx->id)->sum('amount');
        $this->assertEquals(0, (int) $sum);
    }

    public function test_pasien_jkn_tidak_dikenakan_iur_biaya_tambahan_pada_rawat_jalan(): void
    {
        $encounter = $this->createBpjsEncounterFixture();

        $invoice = OutpatientInvoice::create([
            'invoice_code' => 'INV-BPJS-03',
            'encounter_id' => $encounter->id,
            'doctor_fee_idr' => 150000,
            'medication_fee_idr' => 20000,
            'total_amount_idr' => 170000,
            'payment_method' => 'BPJS',
            'status' => 'PENDING',
        ]);

        $action = app(SubmitBpjsClaimAction::class);
        $claim = $action->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);

        // Tagihan pasien harus 0 (ditanggung 100% oleh penjamin JKN)
        $this->assertSame(0, $claim->patient_copay_amount_idr ?? 0);
    }

    public function test_klaim_yang_disetujui_bpjs_mempertahankan_status_approved_dan_saldo_piutang(): void
    {
        $encounter = $this->createBpjsEncounterFixture();
        $invoice = OutpatientInvoice::create(['invoice_code' => 'INV-BPJS-04', 'encounter_id' => $encounter->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 0, 'total_amount_idr' => 150000, 'payment_method' => 'BPJS', 'status' => 'PENDING']);
        $claim = app(SubmitBpjsClaimAction::class)->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 150000);

        $adjAction = app(AdjudicateBpjsClaimAction::class);
        $updatedClaim = $adjAction->execute($claim, approved: true);

        $this->assertSame('APPROVED', $updatedClaim->status);
        $this->assertSame('SETTLED', $encounter->fresh()->status);
        // Piutang BPJS tetap ada dan tidak dijurnal balik
        $this->assertLedgerPosted('hsp:bpjs_receivable:IDR', 150000, 'DEBIT');
    }

    public function test_klaim_yang_ditolak_bpjs_memicu_jurnal_balik_piutang_bpjs(): void
    {
        $encounter = $this->createBpjsEncounterFixture();
        $invoice = OutpatientInvoice::create(['invoice_code' => 'INV-BPJS-05', 'encounter_id' => $encounter->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 0, 'total_amount_idr' => 150000, 'payment_method' => 'BPJS', 'status' => 'PENDING']);
        $claim = app(SubmitBpjsClaimAction::class)->execute($invoice, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 150000);

        $adjAction = app(AdjudicateBpjsClaimAction::class);
        $rejectedClaim = $adjAction->execute($claim, approved: false, rejectionReason: 'Kelengkapan berkas rekam medis tidak memenuhi syarat koding');

        $this->assertSame('REJECTED', $rejectedClaim->status);
        $this->assertSame('Kelengkapan berkas rekam medis tidak memenuhi syarat koding', $rejectedClaim->rejection_reason);

        // Verifikasi jurnal pembalik: KREDIT Piutang BPJS Rp 150.000 dan DEBIT Beban Klaim Ditolak Rp 150.000
        $this->assertLedgerPosted('hsp:bpjs_receivable:IDR', 150000, 'CREDIT');
        $this->assertLedgerPosted('hsp:bpjs_rejected_claim:IDR', 150000, 'DEBIT');
    }

    // ==================== OutpatientDocumentNumberingTest.php ====================
    public function test_format_persis_seluruh_dokumen_rawat_jalan(): void
    {
        Carbon::setTestNow('2026-11-02 10:00:00');

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_DOC_NUM'], ['name' => 'Poli Penomoran', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-NUM-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);
        $cashier = $this->createHospitalUser('cashier');

        // 1. MRN & ENC
        $regAction = app(RegisterPatientAndIssueQueueAction::class);
        $res = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien Format Persis',
            nik: '3171010101900099',
            dateOfBirth: Carbon::parse('1990-01-01'),
            bloodType: 'O',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        $this->assertMatchesRegularExpression('/^HSP\/MRN\/\d{6}-\d{5}$/', $res['patient']->mrn);
        $this->assertMatchesRegularExpression('/^HSP\/ENC\/\d{6}-\d{5}$/', $res['encounter']->encounter_code);

        // 2. RX
        $rxAction = app(CreateOutpatientPrescriptionAction::class);
        $prescription = $rxAction->execute($res['encounter'], new PrescriptionItemDTO(
            drugCode: 'MED-01',
            drugName: 'Paracetamol',
            quantity: 5,
            dosageInstructions: '3x1',
            unitPriceIdr: 2000,
        ), $doctorUser);

        $this->assertMatchesRegularExpression('/^HSP\/RX\/\d{6}-\d{5}$/', $prescription->prescription_code);

        // 3. DSP
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi Utama', 'city' => 'Jakarta']);
        $lot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'MED-01',
            'drug_name' => 'Paracetamol',
            'lot_number' => 'LOT-FMT-01',
            'quantity' => 10,
            'expiry_date' => '2027-12-31',
            'unit_cost_idr' => 1000,
            'is_quarantined' => false,
        ]);

        $pharmacist = $this->createHospitalUser('pharmacist');
        $dspAction = app(DispensePrescriptionFefoAction::class);
        $dispensings = $dspAction->execute($prescription, $branch->id, $pharmacist);

        $this->assertNotEmpty($dispensings);
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $dispensings[0]->dispensing_code);

        // 4. INV
        $invAction = app(GenerateOutpatientInvoiceAction::class);
        $invoice = $invAction->execute($res['encounter']);

        $this->assertMatchesRegularExpression('/^HSP\/INV\/\d{6}-\d{5}$/', $invoice->invoice_code);

        // 5. PAY
        $payAction = app(ProcessCashPaymentAction::class);
        $payment = $payAction->execute(new CashPaymentDTO(
            invoiceId: $invoice->id,
            cashReceivedIdr: $invoice->total_amount_idr,
            idempotencyKey: 'IDEMP-FMT-TEST-001',
            cashierUserId: $cashier->id,
        ));

        $this->assertMatchesRegularExpression('/^HSP\/PAY\/\d{6}-\d{5}$/', $payment->payment_code);

        // 6. CLM-BPJS
        $resBpjs = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien BPJS Persis',
            nik: '3171010101900098',
            dateOfBirth: Carbon::parse('1992-05-05'),
            bloodType: 'A',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'BPJS',
            bpjsCardNumber: '0009998887771',
        ));
        $invBpjs = $invAction->execute($resBpjs['encounter']);
        $clmAction = app(SubmitBpjsClaimAction::class);
        $claim = $clmAction->execute($invBpjs, 'Q-5-44-0', 180000);

        $this->assertMatchesRegularExpression('/^HSP\/CLM-BPJS\/\d{6}-\d{5}$/', $claim->claim_code);
    }

    public function test_beberapa_nomor_dalam_satu_transaksi_pola_dsp_multi_item_semuanya_berbeda(): void
    {
        Carbon::setTestNow('2026-11-02 11:00:00');

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_DSP'], ['name' => 'Poli Farmasi DSP', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-DSP-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);
        $pharmacist = $this->createHospitalUser('pharmacist');

        $regAction = app(RegisterPatientAndIssueQueueAction::class);
        $res = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien DSP Multi Lot',
            nik: '3171010101900077',
            dateOfBirth: Carbon::parse('1985-07-15'),
            bloodType: 'B',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        // Resep butuh 25 tablet
        $rxAction = app(CreateOutpatientPrescriptionAction::class);
        $prescription = $rxAction->execute($res['encounter'], new PrescriptionItemDTO(
            drugCode: 'AMX-500',
            drugName: 'Amoxicillin 500mg',
            quantity: 25,
            dosageInstructions: '3x1',
            unitPriceIdr: 3000,
        ), $doctorUser);

        // Buat 3 lot berbeda yang akan diambil secara bertahap oleh alokasi FEFO:
        // Lot 1: 10 tablet (exp 2027-01-01)
        // Lot 2: 10 tablet (exp 2027-02-01)
        // Lot 3: 10 tablet (exp 2027-03-01)
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi Utama', 'city' => 'Jakarta']);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-01',
            'quantity' => 10,
            'expiry_date' => '2027-01-01',
            'unit_cost_idr' => 1200,
            'is_quarantined' => false,
        ]);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-02',
            'quantity' => 10,
            'expiry_date' => '2027-02-01',
            'unit_cost_idr' => 1200,
            'is_quarantined' => false,
        ]);
        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-03',
            'quantity' => 10,
            'expiry_date' => '2027-03-01',
            'unit_cost_idr' => 1200,
            'is_quarantined' => false,
        ]);

        $dspAction = app(DispensePrescriptionFefoAction::class);
        $dispensings = $dspAction->execute($prescription, $branch->id, $pharmacist);

        // Harus terbagi menjadi 3 dispensing: 10, 10, dan 5
        $this->assertCount(3, $dispensings);

        $codes = array_map(fn (OutpatientDispensing $d) => $d->dispensing_code, $dispensings);

        // Seluruh kode harus unik dalam transaksi yang sama
        $this->assertCount(3, array_unique($codes));

        // Format dan keterurutan nomor dokumen gapless
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $codes[0]);
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $codes[1]);
        $this->assertMatchesRegularExpression('/^HSP\/DSP\/\d{6}-\d{5}$/', $codes[2]);
        $this->assertNotSame($codes[0], $codes[1]);
        $this->assertNotSame($codes[1], $codes[2]);
    }

    public function test_record_dihapus_nomor_berikutnya_tidak_mengisi_lubang(): void
    {
        Carbon::setTestNow('2026-11-02 12:00:00');

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_GAP'], ['name' => 'Poli Gapless', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-GAP-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);

        $regAction = app(RegisterPatientAndIssueQueueAction::class);
        $res1 = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien Gap 1',
            nik: '3171010101900011',
            dateOfBirth: Carbon::parse('1991-01-01'),
            bloodType: 'A',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        $invAction = app(GenerateOutpatientInvoiceAction::class);
        $inv1 = $invAction->execute($res1['encounter']);
        $inv1Code = $inv1->invoice_code;

        // Ambil urutan nomor dari invoice 1
        preg_match('/-(\d+)$/', $inv1Code, $m1);
        $num1 = (int) $m1[1];

        // Hapus record invoice 1 dari database
        $inv1->delete();
        $this->assertDatabaseMissing('hsp_outpatient_invoices', ['id' => $inv1->id]);

        // Buat invoice kedua untuk kunjungan baru
        $res2 = $regAction->execute(new RegisterPatientDTO(
            fullName: 'Pasien Gap 2',
            nik: '3171010101900012',
            dateOfBirth: Carbon::parse('1991-02-02'),
            bloodType: 'B',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        ));

        $inv2 = $invAction->execute($res2['encounter']);
        $inv2Code = $inv2->invoice_code;

        preg_match('/-(\d+)$/', $inv2Code, $m2);
        $num2 = (int) $m2[1];

        // Nomor berikutnya harus bertambah (tidak mengisi lubang yang ditinggalkan record yang dihapus)
        $this->assertSame($num1 + 1, $num2, "Nomor invoice {$inv2Code} tidak boleh mengisi lubang invoice {$inv1Code}");
    }

    // ==================== OutpatientEndToEndFlowTest.php ====================
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
        $billingAction = app(GenerateOutpatientInvoiceAction::class);
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
        $billingAction = app(GenerateOutpatientInvoiceAction::class);
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

        $claim = BpjsClaim::where('invoice_id', $invoice->id)->firstOrFail();
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

    // ==================== OutpatientExaminationAndTriageTest.php ====================
    protected function createEncounterFixture(Doctor $doctor, string $status = 'REGISTERED'): OutpatientEncounter
    {
        $patient = Patient::create([
            'mrn' => 'MRN-'.uniqid(),
            'name' => 'Pasien Tes',
            'date_of_birth' => '1990-01-01',
            'blood_type' => 'O',
            'passport_hash' => hash('sha256', 'test-passport'),
        ]);

        $queue = QueueTicket::create([
            'polyclinic_id' => $doctor->polyclinic_id,
            'doctor_id' => $doctor->id,
            'queue_date' => now()->toDateString(),
            'queue_number' => 1,
            'queue_code' => 'TICKET-001',
            'status' => 'WAITING',
        ]);

        return OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $doctor->polyclinic_id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => $status,
        ]);
    }

    public function test_perawat_poli_dapat_mencatat_tanda_tanda_vital_pasien_pada_tahap_triage(): void
    {
        $nurse = $this->createHospitalUser('nurse');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $triageAction = app(RecordVitalSignsAction::class);
        $dto = new VitalSignsDTO(
            systolic: 120,
            diastolic: 80,
            pulse: 78,
            respiratory: 18,
            temperature: 36.6,
        );

        $updated = $triageAction->execute($encounter, $dto, $nurse);

        $this->assertSame(120, $updated->systolic_bp);
        $this->assertSame(80, $updated->diastolic_bp);
        $this->assertSame(78, $updated->pulse_rate);
        $this->assertSame(18, $updated->respiratory_rate);
        $this->assertEquals(36.6, (float) $updated->body_temp_c);
    }

    public function test_perawat_poli_ditolak_saat_mencoba_memasukkan_diagnosis_medis(): void
    {
        $nurse = $this->createHospitalUser('nurse');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Catatan diagnosa terlarang oleh perawat',
            icd10DiagnosisCode: 'K30',
        );

        $this->expectException(AuthorizationException::class);
        $action->execute($encounter, $dto, $nurse);
    }

    public function test_hanya_dokter_yang_bertugas_pada_kunjungan_yang_dapat_mengisi_diagnosis(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Pasien mengalami dispepsia fungsional berulang',
            icd10DiagnosisCode: 'K30',
        );

        $examined = $action->execute($encounter, $dto, $doctorUser);

        $this->assertSame('EXAMINED', $examined->status);
        $this->assertSame('K30', $examined->encrypted_icd10_code);
        $this->assertSame('Pasien mengalami dispepsia fungsional berulang', $examined->encrypted_doctor_notes);
        $this->assertNotNull($examined->examined_at);
    }

    public function test_dokter_dari_poli_lain_ditolak_saat_mencoba_mengubah_rekam_medis_kunjungan_pasien(): void
    {
        $docUser1 = $this->createHospitalUser('doctor');
        $docUser2 = $this->createHospitalUser('doctor');

        $poli1 = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $poli2 = Polyclinic::create(['code' => 'POLI_PED', 'name' => 'Poli Anak', 'is_active' => true]);

        $doctor1 = Doctor::create(['user_id' => $docUser1->id, 'polyclinic_id' => $poli1->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $doctor2 = Doctor::create(['user_id' => $docUser2->id, 'polyclinic_id' => $poli2->id, 'doctor_code' => 'DOC-02', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor1, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Pemeriksaan liar dokter luar',
            icd10DiagnosisCode: 'J00',
        );

        // Dokter 2 mencoba memeriksa pasien dokter 1
        $this->expectException(AuthorizationException::class);
        $action->execute($encounter, $dto, $docUser2);
    }

    public function test_diagnosis_icd10_divalidasi_format_kodenya(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Catatan dokter',
            icd10DiagnosisCode: 'INVALID-CODE-XYZ!@#',
        );

        $this->expectException(\InvalidArgumentException::class);
        $action->execute($encounter, $dto, $doctorUser);
    }

    public function test_catatan_klinis_dokter_tersimpan_dalam_keadaan_terenkripsi_di_database(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $encounter = $this->createEncounterFixture($doctor, status: 'CALLED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $secretClinicalNote = 'Pasien memiliki riwayat rahasia medis keluarga kronis';
        $dto = new DoctorExaminationDTO(
            doctorNotes: $secretClinicalNote,
            icd10DiagnosisCode: 'I10',
        );

        $action->execute($encounter, $dto, $doctorUser);

        // Query database secara mentah tanpa Eloquent decryptor
        $rawRecord = DB::table('hsp_outpatient_encounters')->where('id', $encounter->id)->first();
        $this->assertNotNull($rawRecord);
        $this->assertStringNotContainsString($secretClinicalNote, $rawRecord->encrypted_doctor_notes);
        $this->assertStringNotContainsString('I10', $rawRecord->encrypted_icd10_code);
    }

    public function test_pemeriksaan_dokter_ditolak_jika_pasien_belum_dipanggil_dari_antrian(): void
    {
        $doctorUser = $this->createHospitalUser('doctor');
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        // Pasien masih berstatus REGISTERED (belum CALLED)
        $encounter = $this->createEncounterFixture($doctor, status: 'REGISTERED');

        $action = app(ExaminePatientAndDiagnoseAction::class);
        $dto = new DoctorExaminationDTO(
            doctorNotes: 'Pemeriksaan loncat antrian',
            icd10DiagnosisCode: 'K30',
        );

        $this->expectException(\DomainException::class);
        $action->execute($encounter, $dto, $doctorUser);
    }

    // ==================== OutpatientPharmacyAndFefoTest.php ====================
    protected function createExaminedEncounterFixture(): OutpatientEncounter
    {
        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $patient = Patient::create(['mrn' => 'MRN-'.uniqid(), 'name' => 'Pasien Farmasi', 'date_of_birth' => '1992-05-10', 'blood_type' => 'AB', 'passport_hash' => hash('sha256', 'p')]);
        $queue = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => now()->toDateString(), 'queue_number' => 1, 'queue_code' => 'T-01', 'status' => 'SERVED']);

        return OutpatientEncounter::create([
            'encounter_code' => 'ENC-'.uniqid(),
            'patient_id' => $patient->id,
            'queue_id' => $queue->id,
            'polyclinic_id' => $poli->id,
            'doctor_id' => $doctor->id,
            'payment_method' => 'CASH',
            'status' => 'EXAMINED',
            'encrypted_icd10_code' => 'K30',
        ]);
    }

    public function test_resep_obat_elektronik_hanya_dapat_diterbitkan_oleh_dokter(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $nurse = $this->createHospitalUser('nurse');

        $action = app(CreateOutpatientPrescriptionAction::class);
        $dto = new PrescriptionItemDTO(
            drugCode: 'PCT-500',
            drugName: 'Paracetamol 500mg',
            quantity: 10,
            dosageInstructions: '3 x 1 tablet sesudah makan',
        );

        $this->expectException(AuthorizationException::class);
        $action->execute($encounter, $dto, $nurse);
    }

    public function test_penerbitan_resep_obat_tidak_mengurangi_saldo_stok_di_gudang(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $doctorUser = $encounter->doctor->user;

        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Instalasi Farmasi Utama', 'city' => 'Jakarta', 'is_active' => true]);
        $lot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-001',
            'quantity' => 100,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $action = app(CreateOutpatientPrescriptionAction::class);
        $dto = new PrescriptionItemDTO(
            drugCode: 'AMX-500',
            drugName: 'Amoxicillin 500mg',
            quantity: 20,
            dosageInstructions: '3 x 1 tablet tiap 8 jam',
        );

        $prescription = $action->execute($encounter, $dto, $doctorUser);

        $this->assertSame('PRESCRIBED', $prescription->status);
        $this->assertSame('PRESCRIBED', $encounter->fresh()->status);
        // Stok fisik di gudang harus tetap 100 (belum berkurang saat diresepkan)
        $this->assertSame(100, $lot->fresh()->quantity);
    }

    public function test_penyerahan_obat_oleh_apoteker_memotong_stok_menggunakan_alokasi_fefo(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        // Dua lot: Lot A kedaluwarsa 2 bulan lagi, Lot B kedaluwarsa 6 bulan lagi
        $lotA = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'lot_number' => 'LOT-A-EARLY',
            'quantity' => 15,
            'expiry_date' => now()->addMonths(2)->toDateString(),
            'is_quarantined' => false,
        ]);

        $lotB = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'lot_number' => 'LOT-B-LATE',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 20, // Membutuhkan 20 unit: 15 dari Lot A (habis) + 5 dari Lot B
            'dosage_instructions' => '3x1 tablet',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Verifikasi lot A berkurang 15 (menjadi 0) dan lot B berkurang 5 (menjadi 45)
        $this->assertSame(0, $lotA->fresh()->quantity);
        $this->assertSame(45, $lotB->fresh()->quantity);
        $this->assertSame('DISPENSED', $prescription->fresh()->status);
        $this->assertSame('DISPENSED', $encounter->fresh()->status);
    }

    public function test_batch_obat_yang_telah_kedaluwarsa_dilewati_secara_otomatis(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        // Lot expired
        $expiredLot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'CTM-4',
            'drug_name' => 'Chlorpheniramine 4mg',
            'lot_number' => 'LOT-EXP',
            'quantity' => 50,
            'expiry_date' => now()->subDay()->toDateString(), // Kemarin kedaluwarsa
            'is_quarantined' => false,
        ]);

        // Lot valid
        $freshLot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'CTM-4',
            'drug_name' => 'Chlorpheniramine 4mg',
            'lot_number' => 'LOT-FRESH',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'CTM-4',
            'drug_name' => 'Chlorpheniramine 4mg',
            'quantity' => 10,
            'dosage_instructions' => '1x1 malam hari',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Lot expired tidak boleh tersentuh sama sekali
        $this->assertSame(50, $expiredLot->fresh()->quantity);
        // Lot fresh yang dipotong
        $this->assertSame(40, $freshLot->fresh()->quantity);
    }

    public function test_penyerahan_obat_ditolak_jika_total_stok_tidak_mencukupi(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        $lot = PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AZT-250',
            'drug_name' => 'Azithromycin 250mg',
            'lot_number' => 'LOT-AZT',
            'quantity' => 5, // Hanya ada 5
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'AZT-250',
            'drug_name' => 'Azithromycin 250mg',
            'quantity' => 10, // Meminta 10
            'dosage_instructions' => '1x1 selama 5 hari',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);

        $this->expectException(\DomainException::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Stok tidak boleh berkurang sebagian (atomic rollback)
        $this->assertSame(5, $lot->fresh()->quantity);
    }

    public function test_penyerahan_obat_memposting_jurnal_beban_hpp_dan_persediaan_secara_seimbang(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'lot_number' => 'LOT-AMX-COST',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
            'unit_cost_idr' => 1200, // HPP per tablet Rp 1.200
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'AMX-500',
            'drug_name' => 'Amoxicillin 500mg',
            'quantity' => 10, // Total HPP = 10 x Rp 1.200 = Rp 12.000
            'dosage_instructions' => '3x1 tablet',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Assert ledger entry created: DEBIT hsp:cogs_pharmacy:IDR Rp 12.000, CREDIT hsp:inventory_pharmacy:IDR Rp 12.000
        $this->assertLedgerPosted('hsp:cogs_pharmacy:IDR', 12000, 'DEBIT');
        $this->assertLedgerPosted('hsp:inventory_pharmacy:IDR', 12000, 'CREDIT');
    }

    public function test_resep_obat_yang_sudah_diserahkan_tidak_dapat_diserahkan_kembali(): void
    {
        $encounter = $this->createExaminedEncounterFixture();
        $pharmacist = $this->createHospitalUser('pharmacist');
        $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], ['name' => 'Farmasi', 'city' => 'Jakarta', 'is_active' => true]);

        PharmacyBranchLot::create([
            'branch_id' => $branch->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'lot_number' => 'LOT-ONCE',
            'quantity' => 50,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'is_quarantined' => false,
        ]);

        $prescription = OutpatientPrescription::create([
            'prescription_code' => 'RX-'.uniqid(),
            'encounter_id' => $encounter->id,
            'doctor_id' => $encounter->doctor_id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 10,
            'dosage_instructions' => '3x1',
            'status' => 'PRESCRIBED',
        ]);

        $dispenseAction = app(DispensePrescriptionFefoAction::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);

        // Eksekusi kedua kali harus ditolak
        $this->expectException(\DomainException::class);
        $dispenseAction->execute($prescription, $branch->id, $pharmacist);
    }

    // ==================== OutpatientRegistrationAndQueueTest.php ====================
    public function test_pasien_baru_dapat_didaftarkan_dengan_nik_terenkripsi_dan_blind_index_unik(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create([
            'code' => 'POLI_INT',
            'name' => 'Poli Penyakit Dalam',
            'is_active' => true,
        ]);

        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'polyclinic_id' => $poli->id,
            'doctor_code' => 'DOC-INT-01',
            'sip_number' => 'SIP/2026/001',
            'specialization' => 'Spesialis Penyakit Dalam',
            'is_active' => true,
        ]);

        $dto = new RegisterPatientDTO(
            nik: '3171012345670001',
            fullName: 'Budi Santoso',
            dateOfBirth: Carbon::parse('1985-05-12'),
            bloodType: 'O',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
            bpjsCardNumber: null,
        );

        $result = $action->execute($dto, $regStaff);

        $this->assertInstanceOf(OutpatientEncounter::class, $result['encounter']);
        $this->assertSame(1, $result['queue']->queue_number);
        $this->assertSame('WAITING', $result['queue']->status);
        $this->assertSame('REGISTERED', $result['encounter']->status);

        // NIK harus tersimpan terenkripsi, bukan plaintext
        $rawPatient = DB::table('hsp_patients')->where('id', $result['encounter']->patient_id)->first();
        $this->assertNotNull($rawPatient);
        $this->assertStringNotContainsString('3171012345670001', $rawPatient->national_id_nik ?? '');
        $this->assertNotNull($rawPatient->nik_bidx ?? $rawPatient->nik_blind_index ?? null);
    }

    public function test_pendaftaran_pasien_dengan_nik_sama_ditolak_agar_tidak_ganda(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::create(['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $dto = new RegisterPatientDTO(
            nik: '3171012345670002',
            fullName: 'Siti Rahma',
            dateOfBirth: Carbon::parse('1990-08-20'),
            bloodType: 'A',
            polyclinicId: $poli->id,
            doctorId: $doctor->id,
            paymentMethod: 'CASH',
        );

        // Pendaftaran pertama berhasil
        $action->execute($dto, $regStaff);

        // Pendaftaran kedua pada hari yang sama dengan NIK sama di poli yang sama harus ditolak
        $this->expectException(\DomainException::class);
        $action->execute($dto, $regStaff);
    }

    public function test_pasien_lama_ditemukan_kembali_menggunakan_blind_index_nik(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli1 = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $poli2 = Polyclinic::create(['code' => 'POLI_PED', 'name' => 'Poli Anak', 'is_active' => true]);
        $doc1 = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli1->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $doc2 = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli2->id, 'doctor_code' => 'DOC-02', 'is_active' => true]);

        $nik = '3171012345670003';
        $dto1 = new RegisterPatientDTO(
            nik: $nik,
            fullName: 'Ahmad Fauzi',
            dateOfBirth: Carbon::parse('1988-02-14'),
            bloodType: 'B',
            polyclinicId: $poli1->id,
            doctorId: $doc1->id,
            paymentMethod: 'CASH',
        );

        $first = $action->execute($dto1, $regStaff);
        $patientId = $first['encounter']->patient_id;

        // Kunjungan hari berikutnya ke poli lain memakai NIK yang sama
        Carbon::setTestNow(now()->addDay());

        $dto2 = new RegisterPatientDTO(
            nik: $nik,
            fullName: 'Ahmad Fauzi',
            dateOfBirth: Carbon::parse('1988-02-14'),
            bloodType: 'B',
            polyclinicId: $poli2->id,
            doctorId: $doc2->id,
            paymentMethod: 'CASH',
        );

        $second = $action->execute($dto2, $regStaff);

        // Harus menggunakan record Patient yang sama (tidak membuat record pasien baru)
        $this->assertSame($patientId, $second['encounter']->patient_id);
    }

    public function test_nomor_antrian_diberikan_secara_berurutan_mulai_dari_1_untuk_setiap_poli_per_hari(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poliA = Polyclinic::create(['code' => 'POLI_A', 'name' => 'Poli A', 'is_active' => true]);
        $poliB = Polyclinic::create(['code' => 'POLI_B', 'name' => 'Poli B', 'is_active' => true]);
        $docA = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poliA->id, 'doctor_code' => 'DOC-A', 'is_active' => true]);
        $docB = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poliB->id, 'doctor_code' => 'DOC-B', 'is_active' => true]);

        $resA1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000001', fullName: 'P1', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poliA->id, doctorId: $docA->id, paymentMethod: 'CASH'), $regStaff);
        $resA2 = $action->execute(new RegisterPatientDTO(nik: '3171000000000002', fullName: 'P2', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poliA->id, doctorId: $docA->id, paymentMethod: 'CASH'), $regStaff);
        $resB1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000003', fullName: 'P3', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poliB->id, doctorId: $docB->id, paymentMethod: 'CASH'), $regStaff);

        $this->assertSame(1, $resA1['queue']->queue_number);
        $this->assertSame(2, $resA2['queue']->queue_number);
        $this->assertSame(1, $resB1['queue']->queue_number); // Poli B mulai dari 1
    }

    public function test_dua_pendaftaran_bersamaan_di_poli_yang_sama_menghasilkan_nomor_berurutan_tanpa_lompat(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $res1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000011', fullName: 'Concurrent 1', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'A', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);
        $res2 = $action->execute(new RegisterPatientDTO(nik: '3171000000000012', fullName: 'Concurrent 2', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'B', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);

        $numbers = [$res1['queue']->queue_number, $res2['queue']->queue_number];
        sort($numbers);

        $this->assertSame([1, 2], $numbers);
        $this->assertNotSame($res1['queue']->queue_code, $res2['queue']->queue_code);
    }

    public function test_nomor_antrian_poli_otomatis_ter_reset_kembali_ke_1_pada_hari_berikutnya(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        Carbon::setTestNow(Carbon::parse('2026-10-30 08:00:00'));
        $day1P1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000021', fullName: 'Day1 Patient', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'A', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);
        $this->assertSame(1, $day1P1['queue']->queue_number);

        Carbon::setTestNow(Carbon::parse('2026-10-31 08:00:00'));
        $day2P1 = $action->execute(new RegisterPatientDTO(nik: '3171000000000022', fullName: 'Day2 Patient', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'B', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);
        $this->assertSame(1, $day2P1['queue']->queue_number);
    }

    public function test_pembatalan_antrian_hanya_diizinkan_selama_pasien_masih_berstatus_terdaftar(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $res = $action->execute(new RegisterPatientDTO(nik: '3171000000000031', fullName: 'Cancel Me', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);

        $encounter = $res['encounter'];
        $this->assertSame('REGISTERED', $encounter->status);

        // Batalkan kunjungan
        $encounter->cancel('Pasien mendadak berhalangan');

        $this->assertSame('CANCELLED', $encounter->fresh()->status);
        $this->assertSame('CANCELLED', $res['queue']->fresh()->status);
    }

    public function test_antrian_yang_sudah_dipanggil_atau_diperiksa_tidak_dapat_dibatalkan(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $action = app(RegisterPatientAndIssueQueueAction::class);

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Dalam', 'is_active' => true]);
        $doc = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);

        $res = $action->execute(new RegisterPatientDTO(nik: '3171000000000032', fullName: 'Examined Patient', dateOfBirth: Carbon::parse('1990-01-01'), bloodType: 'O', polyclinicId: $poli->id, doctorId: $doc->id, paymentMethod: 'CASH'), $regStaff);

        $encounter = $res['encounter'];
        $encounter->update(['status' => 'EXAMINED']);

        $this->expectException(\DomainException::class);
        $encounter->cancel('Tidak boleh batal setelah diperiksa');
    }

    // ==================== OutpatientReportAndAuditTest.php ====================
    protected function seedDailyOutpatientActivity(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 10:00:00'));
        $sfx = preg_replace('/[^0-9]/', '', $date);

        $poli = Polyclinic::firstOrCreate(['code' => 'POLI_INT'], ['name' => 'Poli Penyakit Dalam', 'is_active' => true]);
        $doctorUser = $this->createHospitalUser('doctor');
        $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-01'], ['user_id' => $doctorUser->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);
        $cashier = $this->createHospitalUser('cashier');

        TariffVersion::firstOrCreate(['service_code' => 'SRV-DOC-SPECIALIST'], [
            'name' => 'Konsultasi Spesialis',
            'category' => 'DOCTOR_FEE',
            'amount_idr' => 150000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        // Pasien 1: Tunai Rp 170.000 (Dokter Rp 150.000 + Farmasi Rp 20.000)
        $p1 = Patient::create(['mrn' => 'MRN-'.$sfx.'-01', 'name' => 'Pasien Tunai 1', 'date_of_birth' => '1990-01-01', 'blood_type' => 'O', 'passport_hash' => hash('sha256', 'p1-'.$sfx)]);
        $q1 = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => $date, 'queue_number' => 1, 'queue_code' => 'T-'.$sfx.'-01', 'status' => 'SERVED']);
        $enc1 = OutpatientEncounter::create(['encounter_code' => 'ENC-'.$sfx.'-01', 'patient_id' => $p1->id, 'queue_id' => $q1->id, 'polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'payment_method' => 'CASH', 'status' => 'DISPENSED', 'encrypted_icd10_code' => 'K30']);

        OutpatientPrescription::create([
            'prescription_code' => 'RX-'.$sfx.'-01',
            'encounter_id' => $enc1->id,
            'doctor_id' => $doctor->id,
            'drug_code' => 'PCT-500',
            'drug_name' => 'Paracetamol 500mg',
            'quantity' => 10,
            'dosage_instructions' => '3x1',
            'status' => 'DISPENSED',
            'unit_price_idr' => 2000,
            'subtotal_idr' => 20000,
        ]);

        $inv1 = app(GenerateOutpatientInvoiceAction::class)->execute($enc1);

        app(ProcessCashPaymentAction::class)->execute(new CashPaymentDTO(invoiceId: $inv1->id, cashReceivedIdr: 200000, idempotencyKey: 'IDEMP-CASH-'.$sfx.'-01', cashierUserId: $cashier->id));

        // Pasien 2: BPJS Klaim Rp 180.000 (Tarif RS Rp 170.000, Selisih Rp 10.000)
        $p2 = Patient::create(['mrn' => 'MRN-'.$sfx.'-02', 'name' => 'Pasien BPJS 2', 'date_of_birth' => '1992-02-02', 'blood_type' => 'A', 'passport_hash' => hash('sha256', 'p2-'.$sfx)]);
        $q2 = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => $date, 'queue_number' => 2, 'queue_code' => 'T-'.$sfx.'-02', 'status' => 'SERVED']);
        $enc2 = OutpatientEncounter::create(['encounter_code' => 'ENC-'.$sfx.'-02', 'patient_id' => $p2->id, 'queue_id' => $q2->id, 'polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'payment_method' => 'BPJS', 'bpjs_card_number' => '0001234567890', 'status' => 'DISPENSED', 'encrypted_icd10_code' => 'K30']);
        $inv2 = OutpatientInvoice::create(['invoice_code' => 'INV-'.$sfx.'-02', 'encounter_id' => $enc2->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 20000, 'total_amount_idr' => 170000, 'payment_method' => 'BPJS', 'status' => 'PENDING', 'created_at' => now()]);

        app(SubmitBpjsClaimAction::class)->execute($inv2, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);
    }

    public function test_setiap_transaksi_jurnal_ledger_seimbang_debit_dan_kredit(): void
    {
        $this->seedDailyOutpatientActivity('2026-10-30');

        $transactions = DB::table('bank_ledger_transactions')->get();
        $this->assertGreaterThan(0, $transactions->count());

        foreach ($transactions as $tx) {
            $sum = DB::table('bank_ledger_entries')->where('transaction_id', $tx->id)->sum('amount');
            $this->assertEquals(0, (int) $sum, "Transaksi {$tx->type} (ID {$tx->id}) tidak balance: sum is {$sum}");
        }
    }

    public function test_saldo_akun_pendapatan_berada_di_sisi_kredit(): void
    {
        $this->seedDailyOutpatientActivity('2026-10-30');

        $docAcc = DB::table('bank_ledger_accounts')->where('code', 'hsp:doctor_fee_revenue:IDR')->firstOrFail();
        $docRevenue = DB::table('bank_ledger_entries')->where('account_id', $docAcc->id)->sum('amount');
        $this->assertSame(300000, (int) $docRevenue); // 2 pasien x 150.000

        $pharmaAcc = DB::table('bank_ledger_accounts')->where('code', 'hsp:pharmacy_revenue:IDR')->firstOrFail();
        $pharmaRevenue = DB::table('bank_ledger_entries')->where('account_id', $pharmaAcc->id)->sum('amount');
        $this->assertSame(40000, (int) $pharmaRevenue); // 2 x 20.000 = 40.000

        $varAcc = DB::table('bank_ledger_accounts')->where('code', 'hsp:bpjs_claim_variance:IDR')->firstOrFail();
        $variance = DB::table('bank_ledger_entries')->where('account_id', $varAcc->id)->sum('amount');
        $this->assertSame(10000, (int) $variance);
    }

    public function test_rekap_harian_per_poli_sesuai_dengan_saldo_ledger_hari_itu(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        $action = app(GenerateDailyPolyclinicReportAction::class);
        $report = $action->execute($date);

        $this->assertSame(2, $report['total_patients']);
        $this->assertSame(170000, $report['cash_income_idr']);
        $this->assertSame(180000, $report['bpjs_claim_idr']);
        $this->assertSame(350000, $report['total_revenue_idr']);
        $this->assertTrue($report['is_ledger_reconciled']);
    }

    public function test_laporan_harian_memisahkan_data_hari_1_dan_hari_2_tanpa_akumulasi(): void
    {
        // Hari 1: 2026-10-30
        $this->seedDailyOutpatientActivity('2026-10-30');

        // Hari 2: 2026-10-31
        $this->seedDailyOutpatientActivity('2026-10-31');

        $action = app(GenerateDailyPolyclinicReportAction::class);

        // Laporan Hari 1 harus tepat memuat transaksi hari 1 (Rp 170.000 kas, Rp 180.000 BPJS, 2 pasien)
        $rep1 = $action->execute('2026-10-30');
        $this->assertSame(2, $rep1['total_patients']);
        $this->assertSame(170000, $rep1['cash_income_idr']);
        $this->assertSame(180000, $rep1['bpjs_claim_idr']);
        $this->assertSame(350000, $rep1['total_revenue_idr']);
        $this->assertTrue($rep1['is_ledger_reconciled']);

        // Laporan Hari 2 juga harus tepat memuat transaksi hari 2 saja, bukan kumulatif Rp 340.000
        $rep2 = $action->execute('2026-10-31');
        $this->assertSame(2, $rep2['total_patients']);
        $this->assertSame(170000, $rep2['cash_income_idr']);
        $this->assertSame(180000, $rep2['bpjs_claim_idr']);
        $this->assertSame(350000, $rep2['total_revenue_idr']);
        $this->assertTrue($rep2['is_ledger_reconciled']);
    }

    public function test_laporan_harian_mendeteksi_diskrepansi_jika_fixture_posting_salah_tanda(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        // Simulasi fixture posting salah tanda per §A2.1:
        // Kas tunai pada posting tambahan seharusnya bertanda negatif (debit), tetapi diposting kredit (positif)
        app(LedgerService::class)->post(new PostingDTO(
            type: HospitalTransactionTypes::HSP_OUTPATIENT_CASH_PAY->value,
            description: 'Simulasi posting salah tanda: kas kredit penyesuaian',
            idempotencyKey: 'hsp:pay:fixture-wrong-sign-01',
            entries: Posting::lines()
                ->credit('hsp:cash:IDR', 50000)
                ->debit('hsp:patient_receivable:IDR', 50000)
                ->toEntries('IDR'),
            referenceType: 'OUTPATIENT_INVOICE',
            referenceId: '9999',
            postedAt: Carbon::parse($date.' 11:00:00'),
        ));

        $action = app(GenerateDailyPolyclinicReportAction::class);
        $report = $action->execute($date);

        $this->assertFalse($report['is_ledger_reconciled']);
        $this->assertNotEmpty($report['discrepancies']);
        $this->assertStringContainsString('Diskrepansi Kas Tunai', implode(' | ', $report['discrepancies']));
    }

    public function test_command_hsp_audit_exit_0_bersih(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        $exitCode = Artisan::call('hsp:audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Hospital audit PASSED', $output);
        $this->assertStringContainsString('0 discrepancy', $output);
    }

    public function test_command_hsp_audit_mendeteksi_korupsi_kas_tunai(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        // Korupsi data kasir: ubah total_amount_idr invoice tunai langsung di DB tanpa posting ledger
        OutpatientInvoice::where('payment_method', 'CASH')
            ->firstOrFail()
            ->update(['total_amount_idr' => 999999]);

        $exitCode = Artisan::call('hsp:audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('Hospital audit FAILED', $output);
        $this->assertStringContainsStringIgnoringCase('Diskrepansi Kas Tunai', $output);
    }

    public function test_command_hsp_audit_mendeteksi_korupsi_piutang_bpjs(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        // Korupsi data BPJS: ubah nilai klaim di tabel BPJS tanpa penyesuaian ledger
        BpjsClaim::query()->update(['ina_cbg_tariff_idr' => 500000]);

        $exitCode = Artisan::call('hsp:audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('Hospital audit FAILED', $output);
        $this->assertStringContainsStringIgnoringCase('Diskrepansi Piutang BPJS', $output);
    }

    public function test_command_hsp_audit_mendeteksi_korupsi_kuitansi_pembayaran(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        // Korupsi data kuitansi: ubah amount_idr pembayaran kasir sehingga tidak cocok dengan invoice
        DB::table('hsp_outpatient_payments')->update(['amount_idr' => 50000]);

        $exitCode = Artisan::call('hsp:audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('Hospital audit FAILED', $output);
        $this->assertStringContainsStringIgnoringCase('termanipulasi', $output);
    }

    public function test_command_hsp_audit_mendeteksi_korupsi_kapasitas_ranjang(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        // Korupsi kapasitas ranjang: kasur OCCUPIED tanpa referensi kunjungan
        DB::table('hsp_beds')->insert([
            'bed_code' => 'BED-CORRUPT-TEST',
            'ward_name' => 'Ward Acceptance',
            'room_number' => '101',
            'bed_class' => 'VIP',
            'rate_per_day_idr' => 500000,
            'status' => 'OCCUPIED',
            'current_encounter_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('hsp:audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('Hospital audit FAILED', $output);
    }

    // ==================== OutpatientUiAndValidationTest.php ====================
    public function test_halaman_pendaftaran_200_untuk_role_berhak(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');
        $admin = $this->createHospitalUser('admin');

        $responseReg = $this->actingAs($regStaff)->get(route('hospital.outpatient.registration.index'));
        $responseReg->assertOk();

        $responseAdmin = $this->actingAs($admin)->get(route('hospital.outpatient.registration.index'));
        $responseAdmin->assertOk();
    }

    public function test_halaman_pendaftaran_403_untuk_role_lain(): void
    {
        $doctor = $this->createHospitalUser('doctor');
        $pharmacist = $this->createHospitalUser('pharmacist');

        $this->actingAs($doctor)->get(route('hospital.outpatient.registration.index'))->assertForbidden();
        $this->actingAs($pharmacist)->get(route('hospital.outpatient.registration.index'))->assertForbidden();
    }

    public function test_form_pendaftaran_validasi_gagal_kembali_dengan_pesan_bukan_500(): void
    {
        $regStaff = $this->createHospitalUser('hospital_registration');

        // Form post tanpa data wajib
        $response = $this->actingAs($regStaff)->post(route('hospital.outpatient.registration.store'), [
            'nik' => '', // Kosong -> invalid
        ]);

        $this->assertNotSame(500, $response->status());
        $response->assertRedirect();
        $response->assertSessionHasErrors(['nik']);
    }

    public function test_halaman_antrian_200_untuk_perawat_dan_dokter(): void
    {
        $nurse = $this->createHospitalUser('nurse');
        $doctor = $this->createHospitalUser('doctor');

        $this->actingAs($nurse)->get(route('hospital.outpatient.queue.index'))->assertOk();
        $this->actingAs($doctor)->get(route('hospital.outpatient.queue.index'))->assertOk();
    }

    public function test_halaman_kasir_200_untuk_kasir_dan_finance(): void
    {
        $cashier = $this->createHospitalUser('cashier');
        $finance = $this->createHospitalUser('hospital_finance');

        $this->actingAs($cashier)->get(route('hospital.outpatient.cashier.index'))->assertOk();
        $this->actingAs($finance)->get(route('hospital.outpatient.cashier.index'))->assertOk();
    }

    public function test_halaman_laporan_harian_200_untuk_finance_dan_auditor(): void
    {
        $finance = $this->createHospitalUser('hospital_finance');
        $auditor = $this->createHospitalUser('auditor');

        $this->actingAs($finance)->get(route('hospital.outpatient.reports.daily'))->assertOk();
        $this->actingAs($auditor)->get(route('hospital.outpatient.reports.daily'))->assertOk();
    }
}
