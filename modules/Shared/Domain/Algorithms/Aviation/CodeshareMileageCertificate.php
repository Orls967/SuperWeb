<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class CodeshareMileageCertificate
{
    public function __construct(
        public readonly int $grossFareMinor,
        public readonly int $operatingShareMinor,
        public readonly int $marketingShareMinor,
        public readonly int $milesAccrued,
        public readonly string $sha256Hash
    ) {}

    public static function create(int $gross, int $opShare, int $mktShare, int $miles): self
    {
        $payload = json_encode([
            'gross' => $gross,
            'operating_share' => $opShare,
            'marketing_share' => $mktShare,
            'miles' => $miles,
        ], JSON_THROW_ON_ERROR);

        return new self($gross, $opShare, $mktShare, $miles, hash('sha256', $payload));
    }
}

