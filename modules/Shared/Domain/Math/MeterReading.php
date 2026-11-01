<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Value object representing a single meter register reading.
 */
final readonly class MeterReading
{
    public function __construct(
        public int $timestamp,
        public int $register,
        public bool $isReset = false,
    ) {
        if ($timestamp < 0) {
            throw new InvalidArgumentException("Timestamp cannot be negative: {$timestamp}");
        }
        if ($register < 0) {
            throw new InvalidArgumentException("Register reading cannot be negative: {$register}");
        }
    }
}
