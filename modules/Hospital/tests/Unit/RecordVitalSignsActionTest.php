<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Actions\RecordVitalSignsAction;
use Modules\Hospital\Application\DTOs\VitalSignsDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(RecordVitalSignsAction::class);

function createEncounterForVitals(string $suffix): OutpatientEncounter
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
        'status' => 'WAITING',
    ]);

    return OutpatientEncounter::create([
        'encounter_code' => "ENC-{$suffix}",
        'patient_id' => $patient->id,
        'queue_id' => $queue->id,
        'polyclinic_id' => $poli->id,
        'doctor_id' => $doctor->id,
        'payment_method' => 'CASH',
        'status' => 'REGISTERED',
    ]);
}

it('records vital signs with nurse role', function () {
    $encounter = createEncounterForVitals('VS-01');
    $nurse = User::create(['name' => 'Nurse 1', 'email' => 'nurse_vs01@test.local', 'password' => 'secret', 'role' => 'nurse']);

    $dto = new VitalSignsDTO(120, 80, 75, 18, 36.6);
    $action = app(RecordVitalSignsAction::class);
    $updated = $action->execute($encounter, $dto, $nurse);

    expect($updated->systolic_bp)->toBe(120)
        ->and($updated->diastolic_bp)->toBe(80)
        ->and($updated->pulse_rate)->toBe(75)
        ->and($updated->respiratory_rate)->toBe(18)
        ->and((float) $updated->body_temp_c)->toBe(36.6);
});

it('records vital signs with doctor, admin, or null actor', function () {
    $encounter = createEncounterForVitals('VS-02');
    $doctor = User::create(['name' => 'Doctor 1', 'email' => 'doc_vs02@test.local', 'password' => 'secret', 'role' => 'doctor']);
    $admin = User::create(['name' => 'Admin 1', 'email' => 'adm_vs02@test.local', 'password' => 'secret', 'role' => 'admin']);

    $dto = new VitalSignsDTO(110, 70, 68, 16, 36.4);
    $action = app(RecordVitalSignsAction::class);

    $up1 = $action->execute($encounter, $dto, $doctor);
    expect($up1->systolic_bp)->toBe(110);

    $up2 = $action->execute($encounter, $dto, $admin);
    expect($up2->systolic_bp)->toBe(110);

    $up3 = $action->execute($encounter, $dto, null);
    expect($up3->systolic_bp)->toBe(110);
});

it('throws AuthorizationException when unauthorized user records vitals', function () {
    $encounter = createEncounterForVitals('VS-03');
    $clerk = User::create(['name' => 'Clerk', 'email' => 'clerk_vs03@test.local', 'password' => 'secret', 'role' => 'clerk']);

    $dto = new VitalSignsDTO(120, 80, 75, 18, 36.6);
    $action = app(RecordVitalSignsAction::class);
    $action->execute($encounter, $dto, $clerk);
})->throws(AuthorizationException::class, 'Hanya perawat dan staf medis berwenang mencatat tanda vital.');
