<?php

declare(strict_types=1);

use Modules\Hospital\Application\DTOs\DoctorExaminationDTO;
use Tests\TestCase;

uses(TestCase::class);

covers(DoctorExaminationDTO::class);

it('instantiates valid doctor examination dto', function () {
    $dto = new DoctorExaminationDTO('Pasien mengeluh mual', 'K30');
    expect($dto->doctorNotes)->toBe('Pasien mengeluh mual')
        ->and($dto->icd10DiagnosisCode)->toBe('K30');
});

it('rejects empty icd10 code', function () {
    new DoctorExaminationDTO('Catatan', '   ');
})->throws(InvalidArgumentException::class);
