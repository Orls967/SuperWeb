<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class PassengerCareCertificate
{
    public function __construct(
        public readonly string $category,
        public readonly int $delayMinutes,
        public readonly bool $dutyOfCareRequired,
        public readonly int $cashCompensationMinor,
        public readonly string $sha256Hash
    ) {}

    public static function create(string $cat, int $delay, bool $care, int $cash): self
    {
        $payload = json_encode([
            'category' => $cat,
            'delay' => $delay,
            'duty_of_care' => $care,
            'cash' => $cash,
        ], JSON_THROW_ON_ERROR);

        return new self($cat, $delay, $care, $cash, hash('sha256', $payload));
    }
}
