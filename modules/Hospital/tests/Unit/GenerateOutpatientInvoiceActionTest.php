<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\GenerateOutpatientInvoiceAction;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Modules\Hospital\Domain\Models\TariffVersion;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(GenerateOutpatientInvoiceAction::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->clock = new class implements ClockInterface
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-11-02 12:00:00');
        }
    };
    $this->app->instance(ClockInterface::class, $this->clock);
});

function createBillingEncounter(string $suffix, string $paymentMethod = 'CASH', int $medFee = 50000): OutpatientEncounter
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
        'payment_method' => $paymentMethod,
        'status' => 'DISPENSED',
    ]);

    if ($medFee > 0) {
        OutpatientPrescription::create([
            'prescription_code' => "RX-{$suffix}",
            'encounter_id' => $encounter->id,
            'doctor_id' => $doctor->id,
            'drug_code' => 'DRUG-01',
            'drug_name' => 'Medicine',
            'quantity' => 1,
            'dosage_instructions' => '1x1',
            'unit_price_idr' => $medFee,
            'subtotal_idr' => $medFee,
            'status' => 'DISPENSED',
        ]);
    }

    return $encounter;
}

it('generates cash invoice with custom tariff and posts billing to ledger', function () {
    TariffVersion::create([
        'service_code' => 'SRV-DOC-SPECIALIST',
        'name' => 'Tarif Dokter Spesialis',
        'category' => 'CONSULTATION',
        'amount_idr' => 175000,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $encounter = createBillingEncounter('INV-01', 'CASH', 50000);

    $action = app(GenerateOutpatientInvoiceAction::class);
    $invoice = $action->execute($encounter);

    expect($invoice->invoice_code)->toStartWith('HSP/INV/')
        ->and($invoice->doctor_fee_idr)->toBe(175000)
        ->and($invoice->medication_fee_idr)->toBe(50000)
        ->and($invoice->total_amount_idr)->toBe(225000)
        ->and($invoice->status)->toBe('PENDING')
        ->and($encounter->fresh()->status)->toBe('INVOICED');

    // Assert ledger debit receivable, credit doctor fee, credit pharmacy
    $recAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:patient_receivable:IDR')->first();
    expect($recAccount)->not->toBeNull();

    $entry = DB::table('bank_ledger_entries')
        ->where('account_id', $recAccount->id)
        ->whereRaw('CAST(amount AS INTEGER) = ?', [-225000])
        ->first();
    expect($entry)->not->toBeNull();
});

it('generates bpjs invoice with default tariff without initial cash ledger billing', function () {
    $encounter = createBillingEncounter('INV-02', 'BPJS', 30000);

    $action = app(GenerateOutpatientInvoiceAction::class);
    $invoice = $action->execute($encounter);

    expect($invoice->doctor_fee_idr)->toBe(150000)
        ->and($invoice->medication_fee_idr)->toBe(30000)
        ->and($invoice->total_amount_idr)->toBe(180000)
        ->and($invoice->payment_method)->toBe('BPJS')
        ->and($encounter->fresh()->status)->toBe('INVOICED');
});
