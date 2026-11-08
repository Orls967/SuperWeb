<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class PassengerReaccommodationCertificate
{
    public function __construct(
        public readonly int $passengerCount,
        public readonly array $prioritizedQueue,
        public readonly string $sha256Hash
    ) {}

    public static function create(int $count, array $queue): self
    {
        $payload = json_encode([
            'count' => $count,
            'queue' => $queue,
        ], JSON_THROW_ON_ERROR);

        return new self($count, $queue, hash('sha256', $payload));
    }
}

