<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\GenerateOutpatientInvoiceAction;
use Modules\Hospital\Application\Actions\ProcessCashPaymentAction;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Modules\Hospital\Domain\Models\TariffVersion;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientBillingAndCashierTest extends HospitalAcceptanceHelper
{
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
            ->where('idempotency_key', 'HSP-PAY-'.$key)
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
}
