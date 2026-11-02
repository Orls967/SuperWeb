<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hospital\Application\Actions\RegisterPatientAndIssueQueueAction;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\Polyclinic;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(RegisterPatientAndIssueQueueAction::class);

beforeEach(function () {
    $this->clock = new class implements ClockInterface
    {
        public function now(): CarbonImmutable
        {
            return CarbonImmutable::parse('2026-11-02 08:30:00');
        }
    };
    $this->app->instance(ClockInterface::class, $this->clock);
});

function setupDoctorAndPoli(string $code = 'POLI_REG'): array
{
    $poli = Polyclinic::firstOrCreate(['code' => $code], ['name' => "Poli {$code}", 'is_active' => true]);
    $user = User::firstOrCreate(['email' => "doc_{$code}@hospital.local"], ['name' => "Doc {$code}", 'password' => 'secret', 'role' => 'doctor']);
    $doctor = Doctor::firstOrCreate(['doctor_code' => "DOC-{$code}"], ['user_id' => $user->id, 'polyclinic_id' => $poli->id, 'is_active' => true]);

    return [$poli, $doctor];
}

it('registers new cash patient and issues queue and encounter', function () {
    [$poli, $doctor] = setupDoctorAndPoli('POLI_A');

    $dto = new RegisterPatientDTO(
        nik: '3201010101010099',
        fullName: 'Budi Santoso',
        dateOfBirth: Carbon::parse('1985-05-20'),
        bloodType: 'B',
        polyclinicId: $poli->id,
        doctorId: $doctor->id,
        paymentMethod: 'CASH',
        bpjsCardNumber: null
    );

    $action = app(RegisterPatientAndIssueQueueAction::class);
    $result = $action->execute($dto);

    expect($result['patient']->mrn)->toStartWith('HSP/MRN/')
        ->and($result['patient']->name)->toBe('Budi Santoso')
        ->and($result['queue']->queue_number)->toBe(1)
        ->and($result['queue']->status)->toBe('WAITING')
        ->and($result['encounter']->encounter_code)->toStartWith('HSP/ENC/')
        ->and($result['encounter']->status)->toBe('REGISTERED');
});

it('registers bpjs patient successfully when card number provided', function () {
    [$poli, $doctor] = setupDoctorAndPoli('POLI_B');

    $dto = new RegisterPatientDTO(
        nik: '3201010101010088',
        fullName: 'Siti Aminah',
        dateOfBirth: Carbon::parse('1992-08-15'),
        bloodType: 'A',
        polyclinicId: $poli->id,
        doctorId: $doctor->id,
        paymentMethod: 'BPJS',
        bpjsCardNumber: '0001234567890'
    );

    $action = app(RegisterPatientAndIssueQueueAction::class);
    $result = $action->execute($dto);

    expect($result['encounter']->payment_method)->toBe('BPJS')
        ->and($result['encounter']->bpjs_card_number)->toBe('0001234567890');
});

it('prevents duplicate registration on same polyclinic on same day', function () {
    [$poli, $doctor] = setupDoctorAndPoli('POLI_C');

    $dto = new RegisterPatientDTO(
        nik: '3201010101010077',
        fullName: 'Pasien Dobel',
        dateOfBirth: Carbon::parse('1990-01-01'),
        bloodType: 'O',
        polyclinicId: $poli->id,
        doctorId: $doctor->id,
        paymentMethod: 'CASH',
        bpjsCardNumber: null
    );

    $action = app(RegisterPatientAndIssueQueueAction::class);
    $action->execute($dto);

    // Registrasi kedua kali di hari dan poli yang sama
    $action->execute($dto);
})->throws(DomainException::class, 'Pasien dengan NIK tersebut sudah terdaftar pada poliklinik ini hari ini.');

it('throws InvalidArgumentException when NIK is blank', function () {
    [$poli, $doctor] = setupDoctorAndPoli('POLI_D');

    $dto = new RegisterPatientDTO(
        nik: '   ',
        fullName: 'Tanpa NIK',
        dateOfBirth: Carbon::parse('1990-01-01'),
        bloodType: 'O',
        polyclinicId: $poli->id,
        doctorId: $doctor->id,
        paymentMethod: 'CASH',
        bpjsCardNumber: null
    );

    $action = app(RegisterPatientAndIssueQueueAction::class);
    $action->execute($dto);
})->throws(InvalidArgumentException::class, 'NIK pasien wajib diisi.');

it('throws InvalidArgumentException when BPJS payment method lacks card number', function () {
    [$poli, $doctor] = setupDoctorAndPoli('POLI_E');

    $dto = new RegisterPatientDTO(
        nik: '3201010101010066',
        fullName: 'BPJS Tanpa Kartu',
        dateOfBirth: Carbon::parse('1990-01-01'),
        bloodType: 'O',
        polyclinicId: $poli->id,
        doctorId: $doctor->id,
        paymentMethod: 'BPJS',
        bpjsCardNumber: null
    );

    $action = app(RegisterPatientAndIssueQueueAction::class);
    $action->execute($dto);
})->throws(InvalidArgumentException::class, 'Nomor kartu BPJS wajib diisi untuk pasien penjamin JKN/BPJS.');
