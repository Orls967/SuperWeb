<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class FuelLogException extends RuntimeException
{
    public static function forbidden(): self
    {
        return new self('Anda tidak berwenang mencatat BBM untuk armada ini.');
    }

    public static function invalidAmount(): self
    {
        return new self('Liter dan harga per liter harus lebih dari nol.');
    }

    public static function odometerNotIncreasing(int $previousM, int $givenM): self
    {
        return new self('Odometer harus lebih besar dari catatan sebelumnya ('.number_format($previousM / 1000, 1, ',', '.').' km); diterima '.number_format($givenM / 1000, 1, ',', '.').' km.');
    }
}
