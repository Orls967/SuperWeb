<?php

declare(strict_types=1);

use Modules\Hospital\Application\DTOs\VitalSignsDTO;
use Tests\TestCase;

uses(TestCase::class);

covers(VitalSignsDTO::class);

it('instantiates valid vital signs dto', function () {
    $dto = new VitalSignsDTO(120, 80, 72, 18, 36.5);
    expect($dto->systolic)->toBe(120)
        ->and($dto->diastolic)->toBe(80)
        ->and($dto->pulse)->toBe(72)
        ->and($dto->respiratory)->toBe(18)
        ->and($dto->temperature)->toBe(36.5);
});

it('accepts systolic value of 1', function () {
    $dto = new VitalSignsDTO(1, 80, 72, 18, 36.5);
    expect($dto->systolic)->toBe(1);
});

it('rejects non-positive systolic BP', function () {
    new VitalSignsDTO(0, 80, 72, 18, 36.5);
})->throws(InvalidArgumentException::class);
