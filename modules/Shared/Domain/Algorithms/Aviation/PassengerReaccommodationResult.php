<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class PassengerReaccommodationResult
{
    public function __construct(
        public readonly int $totalPassengers,
        public readonly array $prioritizedQueue,
        public readonly PassengerReaccommodationCertificate $certificate
    ) {}
}
