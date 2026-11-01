<?php

declare(strict_types=1);

namespace Modules\Vending\Application\Services;

use Modules\Shared\Domain\Math\RouteCertificate;
use Modules\Shared\Domain\Math\RouteCertificateVerifier;

final readonly class RouteOptimizationCertificate
{
    public int $mstLowerBoundMeters;

    public function __construct(
        public RouteCertificate $certificate,
        public int $totalCostMeters,
        public int $lowerBoundMeters,
        public bool $isExact
    ) {
        $this->mstLowerBoundMeters = $lowerBoundMeters;
    }

    public function isValid(): bool
    {
        try {
            RouteCertificateVerifier::verify($this->certificate);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
