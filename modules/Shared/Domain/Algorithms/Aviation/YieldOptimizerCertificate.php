<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class YieldOptimizerCertificate
{
    public function __construct(
        public readonly int $capacity,
        public readonly array $buckets,
        public readonly string $algorithmVersion,
        public readonly string $sha256Hash
    ) {}

    public static function create(int $capacity, array $buckets, string $version): self
    {
        $payload = json_encode([
            'capacity' => $capacity,
            'buckets' => $buckets,
            'version' => $version,
        ], JSON_THROW_ON_ERROR);

        return new self($capacity, $buckets, $version, hash('sha256', $payload));
    }
}

