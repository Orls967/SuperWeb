<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class ResalePriceCap
{
    private const MAX_CAP_PERCENTAGE = 120; // 120% dari face value (cap legal anti-scalping)

    public readonly int $faceValueIdr;

    public readonly int $maxAllowedPriceIdr;

    public function __construct(int $faceValueIdr)
    {
        if ($faceValueIdr <= 0) {
            throw new \InvalidArgumentException('Face value harus positif.');
        }

        $this->faceValueIdr = $faceValueIdr;
        $this->maxAllowedPriceIdr = (int) intdiv($faceValueIdr * self::MAX_CAP_PERCENTAGE, 100);
    }

    public function isWithinLegalCap(int $proposedPriceIdr): bool
    {
        return $proposedPriceIdr > 0 && $proposedPriceIdr <= $this->maxAllowedPriceIdr;
    }

    public function getSurplusOverFaceValue(int $resalePriceIdr): int
    {
        return max(0, $resalePriceIdr - $this->faceValueIdr);
    }
}
