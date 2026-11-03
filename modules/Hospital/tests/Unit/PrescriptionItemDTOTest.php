<?php

declare(strict_types=1);

use Modules\Hospital\Application\DTOs\PrescriptionItemDTO;
use Tests\TestCase;

uses(TestCase::class);

covers(PrescriptionItemDTO::class);

it('instantiates valid prescription item dto with explicit unit price', function () {
    $dto = new PrescriptionItemDTO('DRUG-01', 'Paracetamol', 10, '3x1', 2500);
    expect($dto->drugCode)->toBe('DRUG-01')
        ->and($dto->drugName)->toBe('Paracetamol')
        ->and($dto->quantity)->toBe(10)
        ->and($dto->dosageInstructions)->toBe('3x1')
        ->and($dto->unitPriceIdr)->toBe(2500);
});

it('uses default unit price 2000 and boundary quantity 1', function () {
    $dto = new PrescriptionItemDTO('DRUG-01', 'Paracetamol', 1, '3x1');
    expect($dto->quantity)->toBe(1)
        ->and($dto->unitPriceIdr)->toBe(2000);
});

it('rejects zero quantity', function () {
    new PrescriptionItemDTO('DRUG-01', 'Paracetamol', 0, '3x1', 2500);
})->throws(InvalidArgumentException::class);

it('rejects negative quantity', function () {
    new PrescriptionItemDTO('DRUG-01', 'Paracetamol', -1, '3x1', 2500);
})->throws(InvalidArgumentException::class);
