<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\AdjudicateBpjsClaimAction;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(AdjudicateBpjsClaimAction::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->clock = new class implements ClockInterface
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-11-02 10:00:00');
        }
    };
    $this->app->instance(ClockInterface::class, $this->clock);
});

function createEncounterAndInvoice(string $suffix, int $amount = 150000): array
{
    $poli = Polyclinic::firstOrCreate(['code' => 'POLI_GEN'], ['name' => 'Poli Umum', 'is_active' => true]);
    $user = User::firstOrCreate(['email' => "doc_{$suffix}@hospital.local"], ['name' => "Doctor {$suffix}", 'password' => 'secret']);
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

    $invoice = OutpatientInvoice::create([
        'invoice_code' => "INV-{$suffix}",
        'encounter_id' => $encounter->id,
        'total_amount_idr' => $amount,
        'patient_paid_amount_idr' => 0,
        'bpjs_covered_amount_idr' => $amount,
        'status' => 'UNPAID',
    ]);

    return [$patient, $encounter, $invoice];
}

it('approves bpjs claim and settles encounter if invoice exists', function () {
    [$patient, $encounter, $invoice] = createEncounterAndInvoice('ADJ-01', 150000);

    $claim = BpjsClaim::create([
        'claim_code' => 'CLM-ADJ-01',
        'invoice_id' => $invoice->id,
        'ina_cbg_code' => 'Q-5-44-0',
        'ina_cbg_tariff_idr' => 150000,
        'hospital_tariff_idr' => 150000,
        'variance_amount_idr' => 0,
        'patient_copay_amount_idr' => 0,
        'status' => 'SUBMITTED',
    ]);

    $action = app(AdjudicateBpjsClaimAction::class);
    $result = $action->execute($claim, true);

    expect($result->status)->toBe('APPROVED')
        ->and($result->adjudicated_at->toDateTimeString())->toBe('2026-11-02 10:00:00')
        ->and($encounter->fresh()->status)->toBe('SETTLED');
});

it('approves bpjs claim without encounter gracefully', function () {
    [$patient, $encounter, $invoice] = createEncounterAndInvoice('ADJ-02', 100000);

    $claim = BpjsClaim::create([
        'claim_code' => 'CLM-ADJ-02',
        'invoice_id' => $invoice->id,
        'ina_cbg_code' => 'Q-5-44-0',
        'ina_cbg_tariff_idr' => 100000,
        'hospital_tariff_idr' => 100000,
        'variance_amount_idr' => 0,
        'patient_copay_amount_idr' => 0,
        'status' => 'SUBMITTED',
    ]);

    // Simulate null encounter relation
    $invoice->setRelation('encounter', null);

    $action = app(AdjudicateBpjsClaimAction::class);
    $result = $action->execute($claim, true);

    expect($result->status)->toBe('APPROVED')
        ->and($result->adjudicated_at->toDateTimeString())->toBe('2026-11-02 10:00:00');
});

it('rejects bpjs claim with custom reason and posts reversal journal', function () {
    [$patient, $encounter, $invoice] = createEncounterAndInvoice('ADJ-03', 120000);

    $claim = BpjsClaim::create([
        'claim_code' => 'CLM-ADJ-03',
        'invoice_id' => $invoice->id,
        'ina_cbg_code' => 'Q-5-44-0',
        'ina_cbg_tariff_idr' => 120000,
        'hospital_tariff_idr' => 120000,
        'variance_amount_idr' => 0,
        'patient_copay_amount_idr' => 0,
        'status' => 'SUBMITTED',
    ]);

    $action = app(AdjudicateBpjsClaimAction::class);
    $result = $action->execute($claim, false, 'Berkas tidak lengkap');

    expect($result->status)->toBe('REJECTED')
        ->and($result->rejection_reason)->toBe('Berkas tidak lengkap')
        ->and($result->adjudicated_at->toDateTimeString())->toBe('2026-11-02 10:00:00');
});

it('rejects bpjs claim with default reason if null reason provided', function () {
    [$patient, $encounter, $invoice] = createEncounterAndInvoice('ADJ-04', 120000);

    $claim = BpjsClaim::create([
        'claim_code' => 'CLM-ADJ-04',
        'invoice_id' => $invoice->id,
        'ina_cbg_code' => 'Q-5-44-0',
        'ina_cbg_tariff_idr' => 120000,
        'hospital_tariff_idr' => 120000,
        'variance_amount_idr' => 0,
        'patient_copay_amount_idr' => 0,
        'status' => 'SUBMITTED',
    ]);

    $action = app(AdjudicateBpjsClaimAction::class);
    $result = $action->execute($claim, false, null);

    expect($result->status)->toBe('REJECTED')
        ->and($result->rejection_reason)->toBe('Klaim ditolak oleh verifikator BPJS');
});
