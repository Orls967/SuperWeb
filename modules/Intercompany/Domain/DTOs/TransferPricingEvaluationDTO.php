<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\DTOs;

use Modules\Intercompany\Domain\Algorithms\Certificates\TransferPricingMarkupCertificate;

final class TransferPricingEvaluationDTO
{
    public function __construct(
        public readonly string $method,
        public readonly int $costMinor,
        public readonly int $actualPriceMinor,
        public readonly int $actualMarginBps,
        public readonly int $q1Bps,
        public readonly int $medianBps,
        public readonly int $q3Bps,
        public readonly string $status,
        public readonly int $adjustmentMinor,
        public readonly int $targetPriceMinor,
        public readonly TransferPricingMarkupCertificate $certificate
    ) {}

    public function isCompliant(): bool
    {
        return $this->status === 'COMPLIANT';
    }
}
