<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hospital;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;
use Modules\Hospital\Application\Actions\ProcessCashPaymentAction;
use Modules\Hospital\Application\Actions\SubmitBpjsClaimAction;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\Acceptance\Support\HospitalAcceptanceHelper;

class OutpatientReportAndAuditTest extends HospitalAcceptanceHelper
{
    protected function seedDailyOutpatientActivity(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 10:00:00'));

        $poli = Polyclinic::create(['code' => 'POLI_INT', 'name' => 'Poli Penyakit Dalam', 'is_active' => true]);
        $doctor = Doctor::create(['user_id' => $this->createHospitalUser('doctor')->id, 'polyclinic_id' => $poli->id, 'doctor_code' => 'DOC-01', 'is_active' => true]);
        $cashier = $this->createHospitalUser('cashier');

        // Pasien 1: Tunai Rp 170.000 (Dokter Rp 150.000 + Farmasi Rp 20.000)
        $p1 = Patient::create(['mrn' => 'MRN-01', 'name' => 'Pasien Tunai 1', 'date_of_birth' => '1990-01-01', 'blood_type' => 'O', 'passport_hash' => hash('sha256', 'p1')]);
        $q1 = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => $date, 'queue_number' => 1, 'queue_code' => 'T-01', 'status' => 'SERVED']);
        $enc1 = OutpatientEncounter::create(['encounter_code' => 'ENC-01', 'patient_id' => $p1->id, 'queue_id' => $q1->id, 'polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'payment_method' => 'CASH', 'status' => 'DISPENSED', 'encrypted_icd10_code' => 'K30']);
        $inv1 = OutpatientInvoice::create(['invoice_code' => 'INV-01', 'encounter_id' => $enc1->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 20000, 'total_amount_idr' => 170000, 'payment_method' => 'CASH', 'status' => 'PENDING', 'created_at' => now()]);

        app(ProcessCashPaymentAction::class)->execute(new CashPaymentDTO(invoiceId: $inv1->id, cashReceivedIdr: 200000, idempotencyKey: 'IDEMP-CASH-01', cashierUserId: $cashier->id));

        // Pasien 2: BPJS Klaim Rp 180.000 (Tarif RS Rp 170.000, Selisih Rp 10.000)
        $p2 = Patient::create(['mrn' => 'MRN-02', 'name' => 'Pasien BPJS 2', 'date_of_birth' => '1992-02-02', 'blood_type' => 'A', 'passport_hash' => hash('sha256', 'p2')]);
        $q2 = QueueTicket::create(['polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'queue_date' => $date, 'queue_number' => 2, 'queue_code' => 'T-02', 'status' => 'SERVED']);
        $enc2 = OutpatientEncounter::create(['encounter_code' => 'ENC-02', 'patient_id' => $p2->id, 'queue_id' => $q2->id, 'polyclinic_id' => $poli->id, 'doctor_id' => $doctor->id, 'payment_method' => 'BPJS', 'bpjs_card_number' => '0001234567890', 'status' => 'DISPENSED', 'encrypted_icd10_code' => 'K30']);
        $inv2 = OutpatientInvoice::create(['invoice_code' => 'INV-02', 'encounter_id' => $enc2->id, 'doctor_fee_idr' => 150000, 'medication_fee_idr' => 20000, 'total_amount_idr' => 170000, 'payment_method' => 'BPJS', 'status' => 'PENDING', 'created_at' => now()]);

        app(SubmitBpjsClaimAction::class)->execute($inv2, inaCbgCode: 'Q-5-44-0', packageTariffIdr: 180000);
    }

    public function test_setiap_transaksi_jurnal_ledger_seimbang_debit_dan_kredit(): void
    {
        $this->seedDailyOutpatientActivity('2026-10-30');

        $transactions = DB::table('bank_ledger_transactions')->get();
        $this->assertGreaterThan(0, $transactions->count());

        foreach ($transactions as $tx) {
            $debits = DB::table('bank_ledger_entries')->where('transaction_id', $tx->id)->where('direction', 'DEBIT')->sum('amount');
            $credits = DB::table('bank_ledger_entries')->where('transaction_id', $tx->id)->where('direction', 'CREDIT')->sum('amount');
            $this->assertSame($debits, $credits, "Transaksi {$tx->type} (ID {$tx->id}) tidak balance: Debit {$debits} vs Credit {$credits}");
        }
    }

    public function test_saldo_akun_pendapatan_berada_di_sisi_kredit(): void
    {
        $this->seedDailyOutpatientActivity('2026-10-30');

        $docRevenue = DB::table('bank_ledger_entries')->where('account_code', 'hsp:doctor_fee_revenue:IDR')->where('direction', 'CREDIT')->sum('amount');
        $this->assertSame(300000, (int) $docRevenue); // 2 pasien x 150.000

        $pharmaRevenue = DB::table('bank_ledger_entries')->where('account_code', 'hsp:pharmacy_revenue:IDR')->where('direction', 'CREDIT')->sum('amount');
        $this->assertSame(400000 - 360000, (int) $pharmaRevenue); // 2 x 20.000 = 40.000

        $variance = DB::table('bank_ledger_entries')->where('account_code', 'hsp:bpjs_claim_variance:IDR')->where('direction', 'CREDIT')->sum('amount');
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

    public function test_command_hospital_outpatient_audit_exit_0_bersih(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        $exitCode = Artisan::call('hospital:outpatient-audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('PASSED', $output);
        $this->assertStringContainsString('0 discrepancy', $output);
    }

    public function test_command_hospital_outpatient_audit_exit_non_zero_saat_data_terkorupsi(): void
    {
        $date = '2026-10-30';
        $this->seedDailyOutpatientActivity($date);

        // Korupsi data: ubah total_amount_idr invoice tunai langsung di DB tanpa posting ledger
        DB::table('hsp_outpatient_invoices')
            ->where('invoice_code', 'INV-01')
            ->update(['total_amount_idr' => 999999]);

        $exitCode = Artisan::call('hospital:outpatient-audit', ['--date' => $date]);
        $output = Artisan::output();

        $this->assertNotSame(0, $exitCode);
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('discrepancy detected', $output);
    }
}
