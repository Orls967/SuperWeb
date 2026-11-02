<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Actions\CreateOutpatientPrescriptionAction;
use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(CreateOutpatientPrescriptionAction::class);

function createTestEncounter(string $suffix): OutpatientEncounter
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

    return OutpatientEncounter::create([
        'encounter_code' => "ENC-{$suffix}",
        'patient_id' => $patient->id,
        'queue_id' => $queue->id,
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'payment_method' => 'CASH',
        'status' => 'EXAMINED',
    ]);
}

it('creates prescription successfully with doctor role and updates encounter', function () {
    $encounter = createTestEncounter('RX-01');
    $doctorUser = User::create(['name' => 'Dr Test', 'email' => 'dr_test_rx01@test.local', 'password' => 'secret', 'role' => 'doctor']);

    $dto = new PrescriptionItemDTO('DRUG-AMX', 'Amoxicillin 500mg', 10, '3x1 setelah makan', 2500);
    $action = app(CreateOutpatientPrescriptionAction::class);
    $rx = $action->execute($encounter, $dto, $doctorUser);

    expect($rx->prescription_code)->toStartWith('HSP/RX/')
        ->and($rx->drug_code)->toBe('DRUG-AMX')
        ->and($rx->drug_name)->toBe('Amoxicillin 500mg')
        ->and($rx->quantity)->toBe(10)
        ->and($rx->unit_price_idr)->toBe(2500)
        ->and($rx->subtotal_idr)->toBe(25000)
        ->and($rx->status)->toBe('PRESCRIBED')
        ->and($encounter->fresh()->status)->toBe('PRESCRIBED');
});

it('creates prescription with admin role and with null doctor user', function () {
    $encounter = createTestEncounter('RX-02');
    $adminUser = User::create(['name' => 'Admin User', 'email' => 'admin_rx02@test.local', 'password' => 'secret', 'role' => 'admin']);

    $dto = new PrescriptionItemDTO('DRUG-PCT', 'Paracetamol 500mg', 5, '3x1', 2000);
    $action = app(CreateOutpatientPrescriptionAction::class);

    $rxAdmin = $action->execute($encounter, $dto, $adminUser);
    expect($rxAdmin->subtotal_idr)->toBe(10000);

    $rxNull = $action->execute($encounter, $dto, null);
    expect($rxNull->subtotal_idr)->toBe(10000);
});

it('throws AuthorizationException when non-doctor user tries to create prescription', function () {
    $encounter = createTestEncounter('RX-03');
    $nurseUser = User::create(['name' => 'Nurse User', 'email' => 'nurse_rx03@test.local', 'password' => 'secret', 'role' => 'nurse']);

    $dto = new PrescriptionItemDTO('DRUG-PCT', 'Paracetamol 500mg', 5, '3x1', 2000);
    $action = app(CreateOutpatientPrescriptionAction::class);
    $action->execute($encounter, $dto, $nurseUser);
})->throws(AuthorizationException::class);
