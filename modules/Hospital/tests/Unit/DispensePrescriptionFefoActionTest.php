<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\DispensePrescriptionFefoAction;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\OutpatientPrescription;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(DispensePrescriptionFefoAction::class);

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

function createPrescriptionFixture(string $suffix, int $quantity = 10): array
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
        'status' => 'PRESCRIBED',
    ]);

    $prescription = OutpatientPrescription::create([
        'prescription_code' => "RX-{$suffix}",
        'encounter_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'drug_code' => 'DRUG-FEFO',
        'drug_name' => 'Amoxicillin 500mg',
        'quantity' => $quantity,
        'dosage_instructions' => '3x1',
        'unit_price_idr' => 2000,
        'subtotal_idr' => $quantity * 2000,
        'status' => 'PRESCRIBED',
    ]);

    $branch = PharmacyBranch::firstOrCreate(['branch_code' => 'MAIN'], [
        'name' => 'Instalasi Farmasi Utama',
        'city' => 'Jakarta',
        'type' => 'INTERNAL',
        'is_active' => true,
    ]);

    return [$encounter, $prescription, $branch];
}

it('dispenses prescription using FEFO and creates ledger posting', function () {
    [$encounter, $prescription, $branch] = createPrescriptionFixture('DSP-01', 10);
    $pharmacist = User::create(['name' => 'Apoteker 1', 'email' => 'pharm_dsp01@test.local', 'password' => 'secret', 'role' => 'pharmacist']);

    $lot1 = PharmacyBranchLot::create([
        'branch_id' => $branch->id,
        'drug_code' => 'DRUG-FEFO',
        'drug_name' => 'Amoxicillin 500mg',
        'lot_number' => 'LOT-01-EARLY',
        'expiry_date' => '2026-12-01',
        'quantity' => 4,
        'unit_cost_idr' => 1000,
        'is_quarantined' => false,
    ]);

    $lot2 = PharmacyBranchLot::create([
        'branch_id' => $branch->id,
        'drug_code' => 'DRUG-FEFO',
        'drug_name' => 'Amoxicillin 500mg',
        'lot_number' => 'LOT-02-LATER',
        'expiry_date' => '2027-06-01',
        'quantity' => 10,
        'unit_cost_idr' => 1200,
        'is_quarantined' => false,
    ]);

    $action = app(DispensePrescriptionFefoAction::class);
    $dispensings = $action->execute($prescription, $branch->id, $pharmacist);

    expect(count($dispensings))->toBe(2)
        ->and($dispensings[0]->lot_id)->toBe($lot1->id)
        ->and($dispensings[0]->quantity)->toBe(4)
        ->and($dispensings[0]->total_cogs_idr)->toBe(4000)
        ->and($dispensings[1]->lot_id)->toBe($lot2->id)
        ->and($dispensings[1]->quantity)->toBe(6)
        ->and($dispensings[1]->total_cogs_idr)->toBe(7200)
        ->and($lot1->fresh()->quantity)->toBe(0)
        ->and($lot2->fresh()->quantity)->toBe(4)
        ->and($prescription->fresh()->status)->toBe('DISPENSED')
        ->and($encounter->fresh()->status)->toBe('DISPENSED');

    // Assert ledger debit HPP and credit inventory
    $cogsAccount = DB::table('bank_ledger_accounts')->where('code', 'hsp:cogs_pharmacy:IDR')->first();
    expect($cogsAccount)->not->toBeNull();

    $entry = DB::table('bank_ledger_entries')
        ->where('account_id', $cogsAccount->id)
        ->whereRaw('CAST(amount AS INTEGER) = ?', [-11200])
        ->first();
    expect($entry)->not->toBeNull();
});

it('throws AuthorizationException when non-pharmacist tries to dispense', function () {
    [$encounter, $prescription, $branch] = createPrescriptionFixture('DSP-02', 5);
    $nurse = User::create(['name' => 'Nurse', 'email' => 'nurse_dsp02@test.local', 'password' => 'secret', 'role' => 'nurse']);

    $action = app(DispensePrescriptionFefoAction::class);
    $action->execute($prescription, $branch->id, $nurse);
})->throws(AuthorizationException::class);

it('throws DomainException when prescription is already dispensed', function () {
    [$encounter, $prescription, $branch] = createPrescriptionFixture('DSP-03', 5);
    $prescription->update(['status' => 'DISPENSED']);

    $action = app(DispensePrescriptionFefoAction::class);
    $action->execute($prescription, $branch->id, null);
})->throws(DomainException::class, 'Resep ini sudah pernah diserahkan.');

it('throws DomainException when stock is insufficient', function () {
    [$encounter, $prescription, $branch] = createPrescriptionFixture('DSP-04', 50);

    $action = app(DispensePrescriptionFefoAction::class);
    $action->execute($prescription, $branch->id, null);
})->throws(DomainException::class);
