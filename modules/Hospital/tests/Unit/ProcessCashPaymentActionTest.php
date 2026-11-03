<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\ProcessCashPaymentAction;
use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientInvoice;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(ProcessCashPaymentAction::class);

beforeEach(function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->clock = new class implements ClockInterface
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-11-02 13:00:00');
        }
    };
    $this->app->instance(ClockInterface::class, $this->clock);
});

function createUnpaidInvoice(string $suffix, int $amount = 175000): array
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
        'payment_method' => 'CASH',
        'status' => 'INVOICED',
    ]);

    $invoice = OutpatientInvoice::create([
        'invoice_code' => "INV-{$suffix}",
        'encounter_id' => $encounter->id,
        'payment_method' => 'CASH',
        'doctor_fee_idr' => 150000,
        'medication_fee_idr' => 25000,
        'total_amount_idr' => $amount,
        'patient_paid_amount_idr' => 0,
        'bpjs_covered_amount_idr' => 0,
        'status' => 'PENDING',
    ]);

    return [$encounter, $invoice, $user];
}

it('processes cash payment, gives change, updates invoice and posts to ledger', function () {
    [$encounter, $invoice, $user] = createUnpaidInvoice('PAY-01', 175000);

    $dto = new CashPaymentDTO(
        invoiceId: $invoice->id,
        cashReceivedIdr: 200000,
        cashierUserId: $user->id,
        idempotencyKey: 'key-pay-01'
    );

    $action = app(ProcessCashPaymentAction::class);
    $payment = $action->execute($dto);

    expect($payment->payment_code)->toStartWith('HSP/PAY/')
        ->and($payment->amount_idr)->toBe(175000)
        ->and($payment->cash_received_idr)->toBe(200000)
        ->and($payment->change_given_idr)->toBe(25000)
        ->and($invoice->fresh()->status)->toBe('PAID')
        ->and($encounter->fresh()->status)->toBe('SETTLED');

    // Assert ledger cash debit
    $cashAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:cash:IDR')->first();
    expect($cashAccount)->not->toBeNull();

    $entry = DB::table('bank_ledger_entries')
        ->where('account_id', $cashAccount->id)
        ->whereRaw('CAST(amount AS INTEGER) = ?', [-175000])
        ->first();
    expect($entry)->not->toBeNull();
});

it('returns existing payment when duplicate idempotency key is submitted', function () {
    [$encounter, $invoice, $user] = createUnpaidInvoice('PAY-02', 100000);

    $dto = new CashPaymentDTO($invoice->id, 100000, 'key-pay-02', $user->id);
    $action = app(ProcessCashPaymentAction::class);

    $first = $action->execute($dto);
    $second = $action->execute($dto);

    expect($second->id)->toBe($first->id);
});

it('throws InvalidArgumentException when idempotency key is empty', function () {
    $dto = new CashPaymentDTO(1, 100000, '   ', 1);
    $action = app(ProcessCashPaymentAction::class);
    $action->execute($dto);
})->throws(InvalidArgumentException::class, 'Idempotency-Key wajib disertakan.');

it('throws DomainException when invoice is already paid', function () {
    [$encounter, $invoice, $user] = createUnpaidInvoice('PAY-03', 100000);
    $invoice->update(['status' => 'PAID']);

    $dto = new CashPaymentDTO($invoice->id, 100000, 'key-pay-03', $user->id);
    $action = app(ProcessCashPaymentAction::class);
    $action->execute($dto);
})->throws(DomainException::class, 'Tagihan ini sudah lunas.');

it('throws DomainException when cash received is less than total amount', function () {
    [$encounter, $invoice, $user] = createUnpaidInvoice('PAY-04', 150000);

    $dto = new CashPaymentDTO($invoice->id, 100000, 'key-pay-04', $user->id);
    $action = app(ProcessCashPaymentAction::class);
    $action->execute($dto);
})->throws(DomainException::class);
