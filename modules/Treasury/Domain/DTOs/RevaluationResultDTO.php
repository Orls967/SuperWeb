<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

use Modules\Treasury\Domain\Algorithms\Certificates\FxRevaluationCertificate;

final class RevaluationResultDTO
{
    public function __construct(
        public int $revaluedFunctionalIdr,
        public int $unrealizedGainLossIdr,
        public bool $isAsset,
        public FxRevaluationCertificate $certificate
    ) {}

    public function isGain(): bool
    {
        return $this->unrealizedGainLossIdr > 0;
    }

    public function isLoss(): bool
    {
        return $this->unrealizedGainLossIdr < 0;
    }
}
