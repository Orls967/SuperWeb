<?php

declare(strict_types=1);

use Carbon\Carbon;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Tests\TestCase;

uses(TestCase::class);

covers(RegisterPatientDTO::class);

it('instantiates valid register patient dto', function () {
    $dob = Carbon::parse('1990-01-01');
    $dto = new RegisterPatientDTO('3171010101900001', 'Budi Santoso', $dob, 'O', 1, 2, 'BPJS', '0001234567890', 10);
    expect($dto->nik)->toBe('3171010101900001')
        ->and($dto->fullName)->toBe('Budi Santoso')
        ->and($dto->dateOfBirth)->toBe($dob)
        ->and($dto->bloodType)->toBe('O')
        ->and($dto->polyclinicId)->toBe(1)
        ->and($dto->doctorId)->toBe(2)
        ->and($dto->paymentMethod)->toBe('BPJS')
        ->and($dto->bpjsCardNumber)->toBe('0001234567890')
        ->and($dto->userId)->toBe(10);
});

it('rejects non-positive polyclinic id', function () {
    new RegisterPatientDTO('3171010101900001', 'Budi', Carbon::parse('1990-01-01'), 'O', 0, 2);
})->throws(InvalidArgumentException::class);
