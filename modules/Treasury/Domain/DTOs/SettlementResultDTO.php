<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

final class SettlementResultDTO
{
    public function __construct(
        public int $settlementValueIdr,
        public int $allocatedCarryingIdr,
        public int $realizedGainLossIdr,
        public bool $isAsset
    ) {}

    public function isGain(): bool
    {
        return $this->realizedGainLossIdr > 0;
    }

    public function isLoss(): bool
    {
        return $this->realizedGainLossIdr < 0;
    }
}
