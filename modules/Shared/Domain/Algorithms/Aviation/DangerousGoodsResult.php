<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class DangerousGoodsResult
{
    /**
     * @param bool $isEligible
     * @param list<string> $violations
     * @param DangerousGoodsCertificate $certificate
     */
    public function __construct(
        public readonly bool $isEligible,
        public readonly array $violations,
        public readonly DangerousGoodsCertificate $certificate
    ) {
    }
}

