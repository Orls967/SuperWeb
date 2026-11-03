<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\SubmitBpjsClaimAction;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(SubmitBpjsClaimAction::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->clock = new class implements ClockInterface
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-11-02 14:00:00');
        }
    };
    $this->app->instance(ClockInterface::class, $this->clock);
});

function createBpjsInvoice(string $suffix, int $doctorFee = 150000, int $medFee = 50000): OutpatientInvoice
{
    $poli = Polyclinic::firstOrCreate(['code' => 'POLI_GEN'], ['name' => 'Poli Umum', 'is_active' => true]);
    $user = User::firstOrCreate(['email' => "doc_{$suffix}@hospital.local"], ['name' => "Doctor {$suffix}", 'password' => 'secret', 'role' => 'doctor']);
    $doctor = Doctor::firstOrCreate(['doctor_code' => "DOC-{$suffix}"], ['user_id' => $user->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);

    $patient = Patient::create([
        'mrn' => "MRN-{$suffix}",
        'name' => "Patient {$suffix}",
        'date_of_birth' => '1990-01-01',
        'blood_type' => 'O',
        'passport_hash' => hash('sha256', "MRN-{$suffix}"),
        'national_id_nik' => '3201010101010001',
        'nik_bidx' => hash('sha256', '3201010101010001'),
    ]);

    $queue = QueueTicket::create([
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'queue_date' => '2026-11-02',
        'queue_number' => 1,
        'queue_code' => "Q-{$suffix}",
        'status' => 'SERVED',
    ]);

    $encounter = OutpatientEncounter::create([
        'encounter_code' => "ENC-{$suffix}",
        'patient_id' => $patient->id,
        'queue_id' => $queue->id,
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'payment_method' => 'BPJS',
        'status' => 'INVOICED',
    ]);

    return OutpatientInvoice::create([
        'invoice_code' => "INV-{$suffix}",
        'encounter_id' => $encounter->id,
        'payment_method' => 'BPJS',
        'doctor_fee_idr' => $doctorFee,
        'medication_fee_idr' => $medFee,
        'total_amount_idr' => $doctorFee + $medFee,
        'patient_paid_amount_idr' => 0,
        'bpjs_covered_amount_idr' => $doctorFee + $medFee,
        'status' => 'PENDING',
    ]);
}

it('submits bpjs claim with positive variance and posts ledger journals', function () {
    $invoice = createBpjsInvoice('CLM-01', 150000, 50000); // Hospital total = 200,000

    $action = app(SubmitBpjsClaimAction::class);
    $claim = $action->execute($invoice, 'Q-5-44-0', 250000); // INA-CBG = 250,000 -> Variance = +50,000

    expect($claim->claim_code)->toStartWith('HSP/CLM-BPJS/')
        ->and($claim->ina_cbg_code)->toBe('Q-5-44-0')
        ->and($claim->ina_cbg_tariff_idr)->toBe(250000)
        ->and($claim->hospital_tariff_idr)->toBe(200000)
        ->and($claim->variance_amount_idr)->toBe(50000)
        ->and($claim->status)->toBe('SUBMITTED')
        ->and($invoice->fresh()->status)->toBe('CLAIMED')
        ->and($invoice->fresh()->ina_cbg_package_code)->toBe('Q-5-44-0')
        ->and($invoice->fresh()->ina_cbg_tariff_idr)->toBe(250000)
        ->and($invoice->encounter->fresh()->status)->toBe('CLAIMED');

    // Assert debit BPJS receivable 250,000
    $recAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:bpjs_receivable:IDR')->first();
    expect($recAccount)->not->toBeNull();

    $entry = DB::table('bank_ledger_entries')
        ->where('account_id', $recAccount->id)
        ->whereRaw('CAST(amount AS INTEGER) = ?', [-250000])
        ->first();
    expect($entry)->not->toBeNull();

    // Assert credit variance +50,000
    $varAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:bpjs_claim_variance:IDR')->first();
    expect($varAccount)->not->toBeNull();

    $varEntry = DB::table('bank_ledger_entries')
        ->where('account_id', $varAccount->id)
        ->whereRaw('CAST(amount AS INTEGER) = ?', [50000])
        ->first();
    expect($varEntry)->not->toBeNull();
});

it('submits bpjs claim with negative variance and posts debit variance', function () {
    $invoice = createBpjsInvoice('CLM-02', 150000, 50000); // Hospital total = 200,000

    $action = app(SubmitBpjsClaimAction::class);
    $claim = $action->execute($invoice, 'Q-5-44-0', 180000); // INA-CBG = 180,000 -> Variance = -20,000

    expect($claim->variance_amount_idr)->toBe(-20000)
        ->and($claim->status)->toBe('SUBMITTED');

    // Assert debit variance -20,000
    $varAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:bpjs_claim_variance:IDR')->first();
    $varEntry = DB::table('bank_ledger_entries')
        ->where('account_id', $varAccount->id)
        ->whereRaw('CAST(amount AS INTEGER) = ?', [-20000])
        ->first();
    expect($varEntry)->not->toBeNull();
});

it('submits bpjs claim with zero variance without variance ledger lines', function () {
    $invoice = createBpjsInvoice('CLM-03', 150000, 50000); // Hospital total = 200,000

    $action = app(SubmitBpjsClaimAction::class);
    $claim = $action->execute($invoice, 'Q-5-44-0', 200000); // INA-CBG = 200,000 -> Variance = 0

    expect($claim->variance_amount_idr)->toBe(0)
        ->and($claim->status)->toBe('SUBMITTED');

    $varAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:bpjs_claim_variance:IDR')->first();
    $varEntriesCount = DB::table('bank_ledger_entries')
        ->where('account_id', $varAccount->id)
        ->count();
    expect($varEntriesCount)->toBe(0);
});

it('submits bpjs claim with consultation only and without encounter relation', function () {
    $invoice = createBpjsInvoice('CLM-04', 100000, 0); // doctor fee only
    $invoice->setRelation('encounter', null);

    $action = app(SubmitBpjsClaimAction::class);
    $claim = $action->execute($invoice, 'Q-5-44-0', 100000);

    expect($claim->hospital_tariff_idr)->toBe(100000)
        ->and($claim->variance_amount_idr)->toBe(0);
});

it('submits bpjs claim with medication fee only', function () {
    $invoice = createBpjsInvoice('CLM-05', 0, 75000); // med fee only

    $action = app(SubmitBpjsClaimAction::class);
    $claim = $action->execute($invoice, 'Q-5-44-0', 75000);

    expect($claim->hospital_tariff_idr)->toBe(75000)
        ->and($claim->variance_amount_idr)->toBe(0);
});
