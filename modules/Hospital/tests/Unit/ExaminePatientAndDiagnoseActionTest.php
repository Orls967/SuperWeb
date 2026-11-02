<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\ExaminePatientAndDiagnoseAction;
use Modules\Hospital\Application\DTOs\DoctorExaminationDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(ExaminePatientAndDiagnoseAction::class);

beforeEach(function () {
    $this->clock = new class implements ClockInterface
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-11-02 11:30:00');
        }
    };
    $this->app->instance(ClockInterface::class, $this->clock);
});

function createExamineEncounter(string $suffix, string $status = 'IN_CONSULTATION'): array
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
        'status' => $status,
    ]);

    return [$encounter, $user, $doctor];
}

it('examines patient and diagnoses with valid ICD-10 code', function () {
    [$encounter, $doctorUser] = createExamineEncounter('EXM-01', 'IN_CONSULTATION');

    $dto = new DoctorExaminationDTO(
        doctorNotes: 'Keluhan batuk pilek 3 hari, faring hiperemis',
        icd10DiagnosisCode: 'j00'
    );

    $action = app(ExaminePatientAndDiagnoseAction::class);
    $result = $action->execute($encounter, $dto, $doctorUser);

    expect($result->status)->toBe('EXAMINED')
        ->and($result->encrypted_doctor_notes)->toBe('Keluhan batuk pilek 3 hari, faring hiperemis')
        ->and($result->encrypted_icd10_code)->toBe('J00')
        ->and($result->examined_at->toDateTimeString())->toBe('2026-11-02 11:30:00');
});

it('supports admin user or null doctor user', function () {
    [$encounter] = createExamineEncounter('EXM-02', 'IN_CONSULTATION');
    $admin = User::create(['name' => 'Admin', 'email' => 'admin_exm02@test.local', 'password' => 'secret', 'role' => 'admin']);

    $dto = new DoctorExaminationDTO('Catatan dokter admin', 'K30');
    $action = app(ExaminePatientAndDiagnoseAction::class);

    $result = $action->execute($encounter, $dto, $admin);
    expect($result->status)->toBe('EXAMINED');

    $resultNull = $action->execute($encounter, $dto, null);
    expect($resultNull->status)->toBe('EXAMINED');
});

it('throws AuthorizationException when doctor examines patient assigned to different doctor', function () {
    [$encounter] = createExamineEncounter('EXM-03', 'IN_CONSULTATION');
    $otherDoctor = User::create(['name' => 'Other Doctor', 'email' => 'other_doc@test.local', 'password' => 'secret', 'role' => 'doctor']);

    $dto = new DoctorExaminationDTO('Catatan dokter lain', 'K30');
    $action = app(ExaminePatientAndDiagnoseAction::class);
    $action->execute($encounter, $dto, $otherDoctor);
})->throws(AuthorizationException::class, 'Dokter tidak berwenang memeriksa pasien dokter lain.');

it('throws AuthorizationException when non-doctor user tries to examine', function () {
    [$encounter] = createExamineEncounter('EXM-04', 'IN_CONSULTATION');
    $nurse = User::create(['name' => 'Nurse', 'email' => 'nurse_exm04@test.local', 'password' => 'secret', 'role' => 'nurse']);

    $dto = new DoctorExaminationDTO('Catatan perawat', 'K30');
    $action = app(ExaminePatientAndDiagnoseAction::class);
    $action->execute($encounter, $dto, $nurse);
})->throws(AuthorizationException::class);

it('throws DomainException when encounter status is REGISTERED', function () {
    [$encounter, $doctorUser] = createExamineEncounter('EXM-05', 'REGISTERED');

    $dto = new DoctorExaminationDTO('Catatan status registered', 'K30');
    $action = app(ExaminePatientAndDiagnoseAction::class);
    $action->execute($encounter, $dto, $doctorUser);
})->throws(DomainException::class, 'Pasien belum dipanggil dari antrian.');

it('throws InvalidArgumentException when ICD-10 format is invalid', function () {
    [$encounter, $doctorUser] = createExamineEncounter('EXM-06', 'IN_CONSULTATION');

    $dto = new DoctorExaminationDTO('Catatan ICD invalid', 'INVALID-ICD');
    $action = app(ExaminePatientAndDiagnoseAction::class);
    $action->execute($encounter, $dto, $doctorUser);
})->throws(InvalidArgumentException::class);
