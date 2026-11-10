<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

final class ToleranceMatchDTO
{
    public string $bankLineId;
    public string $bookLineId;
    public int $deltaAmount;
    public int $deltaDays;

    public function __construct(
        public string $bankId,
        public string $bookId,
        public int $bankAmount,
        public int $bookAmount,
        public int $discrepancyAmount,
        public int $dayDifference
    ) {
        $this->bankLineId = $bankId;
        $this->bookLineId = $bookId;
        $this->deltaAmount = $discrepancyAmount;
        $this->deltaDays = $dayDifference;
    }
}
