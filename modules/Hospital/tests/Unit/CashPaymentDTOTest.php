<?php

declare(strict_types=1);

use Modules\Hospital\Application\DTOs\CashPaymentDTO;
use Tests\TestCase;

uses(TestCase::class);

covers(CashPaymentDTO::class);

it('instantiates valid cash payment dto', function () {
    $dto = new CashPaymentDTO(1, 100000, 'KEY-123', 5);
    expect($dto->invoiceId)->toBe(1)
        ->and($dto->cashReceivedIdr)->toBe(100000)
        ->and($dto->idempotencyKey)->toBe('KEY-123')
        ->and($dto->cashierUserId)->toBe(5);
});

it('rejects non-positive invoice id', function () {
    new CashPaymentDTO(0, 100000, 'KEY-123', 5);
})->throws(InvalidArgumentException::class);
