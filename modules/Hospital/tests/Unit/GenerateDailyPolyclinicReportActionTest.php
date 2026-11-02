<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Application\Actions\GenerateDailyPolyclinicReportAction;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\OutpatientPayment;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Modules\Hospital\Ledger\HospitalTransactionTypes;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(GenerateDailyPolyclinicReportAction::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
});

function seedReportData(string $date = '2026-11-02', bool $postLedger = true, bool $corruptPayment = false): array
{
    $poli = Polyclinic::firstOrCreate(['code' => 'POLI_RPT'], ['name' => 'Poli Laporan', 'is_active' => true]);
    $user = User::firstOrCreate(['email' => 'doc_rpt@hospital.local'], ['name' => 'Doc Rpt', 'password' => 'secret', 'role' => 'doctor']);
    $doctor = Doctor::firstOrCreate(['doctor_code' => 'DOC-RPT'], ['user_id' => $user->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);

    $p1 = Patient::create([
        'mrn' => 'MRN-RPT-01',
        'name' => 'Patient 1',
        'date_of_birth' => '1990-01-01',
        'blood_type' => 'O',
        'passport_hash' => hash('sha256', 'MRN-RPT-01'),
        'national_id_nik' => '3201010101010001',
        'nik_bidx' => hash('sha256', '3201010101010001'),
    ]);

    $q1 = QueueTicket::create([
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'queue_date' => $date,
        'queue_number' => 1,
        'queue_code' => 'Q-RPT-01',
        'status' => 'SERVED',
    ]);

    $enc1 = OutpatientEncounter::create([
        'encounter_code' => 'ENC-RPT-01',
        'patient_id' => $p1->id,
        'queue_id' => $q1->id,
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'payment_method' => 'CASH',
        'status' => 'SETTLED',
        'created_at' => "{$date} 09:00:00",
    ]);

    $inv1 = OutpatientInvoice::create([
        'invoice_code' => 'INV-RPT-01',
        'encounter_id' => $enc1->id,
        'payment_method' => 'CASH',
        'total_amount_idr' => 150000,
        'patient_paid_amount_idr' => 150000,
        'bpjs_covered_amount_idr' => 0,
        'status' => 'PAID',
        'created_at' => "{$date} 09:30:00",
    ]);

    OutpatientPayment::create([
        'payment_code' => 'PAY-RPT-01',
        'invoice_id' => $inv1->id,
        'payment_method' => 'CASH',
        'amount_idr' => $corruptPayment ? 50000 : 150000,
        'cash_received_idr' => $corruptPayment ? 50000 : 150000,
        'change_given_idr' => 0,
        'idempotency_key' => 'idemp-rpt-01',
        'cashier_user_id' => $user->id,
        'paid_at' => "{$date} 09:30:00",
        'created_at' => "{$date} 09:30:00",
    ]);

    // Pasien 2 (BPJS)
    $p2 = Patient::create([
        'mrn' => 'MRN-RPT-02',
        'name' => 'Patient 2',
        'date_of_birth' => '1990-01-01',
        'blood_type' => 'O',
        'passport_hash' => hash('sha256', 'MRN-RPT-02'),
        'national_id_nik' => '3201010101010002',
        'nik_bidx' => hash('sha256', '3201010101010002'),
    ]);

    $q2 = QueueTicket::create([
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'queue_date' => $date,
        'queue_number' => 2,
        'queue_code' => 'Q-RPT-02',
        'status' => 'SERVED',
    ]);

    $enc2 = OutpatientEncounter::create([
        'encounter_code' => 'ENC-RPT-02',
        'patient_id' => $p2->id,
        'queue_id' => $q2->id,
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'payment_method' => 'BPJS',
        'status' => 'SETTLED',
        'created_at' => "{$date} 10:00:00",
    ]);

    $inv2 = OutpatientInvoice::create([
        'invoice_code' => 'INV-RPT-02',
        'encounter_id' => $enc2->id,
        'payment_method' => 'BPJS',
        'total_amount_idr' => 200000,
        'patient_paid_amount_idr' => 0,
        'bpjs_covered_amount_idr' => 200000,
        'status' => 'PAID',
        'created_at' => "{$date} 10:30:00",
    ]);

    BpjsClaim::create([
        'claim_code' => 'CLM-RPT-02',
        'invoice_id' => $inv2->id,
        'ina_cbg_code' => 'Q-5-44-0',
        'ina_cbg_tariff_idr' => 200000,
        'hospital_tariff_idr' => 200000,
        'variance_amount_idr' => 0,
        'patient_copay_amount_idr' => 0,
        'status' => 'APPROVED',
        'created_at' => "{$date} 10:30:00",
    ]);

    // Pasien 3 (Cancelled)
    $enc3 = OutpatientEncounter::create([
        'encounter_code' => 'ENC-RPT-03',
        'patient_id' => $p1->id,
        'queue_id' => $q1->id,
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'payment_method' => 'CASH',
        'status' => 'CANCELLED',
        'created_at' => "{$date} 11:00:00",
    ]);

    if ($postLedger) {
        $ledger = app(LedgerService::class);
        // Post cash debit 150000 (credit receivable)
        $ledger->post(new PostingDTO(
            type: HospitalTransactionTypes::HSP_OUTPATIENT_CASH_PAY->value,
            description: "Pembayaran kasir {$inv1->invoice_code}",
            idempotencyKey: "rpt:cash:{$inv1->id}",
            entries: Posting::lines()
                ->debit('hsp:cash:IDR', 150000)
                ->credit('hsp:patient_receivable:IDR', 150000)
                ->toEntries('IDR'),
            referenceType: 'HSP_INVOICE',
            referenceId: (string) $inv1->id,
        ));

        // Post BPJS receivable debit 200000 (credit receivable)
        $ledger->post(new PostingDTO(
            type: HospitalTransactionTypes::HSP_OUTPATIENT_BPJS_CLAIM->value,
            description: "Klaim BPJS {$inv2->invoice_code}",
            idempotencyKey: "rpt:bpjs:{$inv2->id}",
            entries: Posting::lines()
                ->debit('hsp:bpjs_receivable:IDR', 200000)
                ->credit('hsp:patient_receivable:IDR', 200000)
                ->toEntries('IDR'),
            referenceType: 'HSP_INVOICE',
            referenceId: (string) $inv2->id,
        ));
    }

    return [$poli, $enc1, $enc2, $enc3];
}

it('generates fully reconciled daily report', function () {
    seedReportData('2026-11-02', postLedger: true, corruptPayment: false);

    $action = app(GenerateDailyPolyclinicReportAction::class);
    $report = $action->execute('2026-11-02');

    expect($report['date'])->toBe('2026-11-02')
        ->and($report['total_patients'])->toBe(2)
        ->and($report['cash_income_idr'])->toBe(150000)
        ->and($report['bpjs_claim_idr'])->toBe(200000)
        ->and($report['total_revenue_idr'])->toBe(350000)
        ->and($report['is_ledger_reconciled'])->toBeTrue()
        ->and($report['discrepancies'])->toBeEmpty()
        ->and(count($report['by_polyclinic']))->toBeGreaterThanOrEqual(1);
});

it('detects cash and bpjs discrepancies when ledger postings are absent', function () {
    seedReportData('2026-11-02', postLedger: false, corruptPayment: false);

    $action = app(GenerateDailyPolyclinicReportAction::class);
    $report = $action->execute('2026-11-02');

    expect($report['is_ledger_reconciled'])->toBeFalse()
        ->and(count($report['discrepancies']))->toBe(2)
        ->and($report['discrepancies'][0])->toContain('Diskrepansi Kas Tunai')
        ->and($report['discrepancies'][1])->toContain('Diskrepansi Piutang BPJS');
});

it('detects corrupted invoice when payment amount does not match invoice total', function () {
    seedReportData('2026-11-02', postLedger: true, corruptPayment: true);

    $action = app(GenerateDailyPolyclinicReportAction::class);
    $report = $action->execute('2026-11-02');

    expect($report['is_ledger_reconciled'])->toBeFalse()
        ->and(collect($report['discrepancies'])->contains(fn ($d) => str_contains($d, 'tagihan kasir termanipulasi')))->toBeTrue();
});
