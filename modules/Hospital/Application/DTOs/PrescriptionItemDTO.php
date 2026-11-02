<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class PrescriptionItemDTO
{
    public function __construct(
        public string $drugCode,
        public string $drugName,
        public int $quantity,
        public string $dosageInstructions,
        public int $unitPriceIdr = 2000,
    ) {
        if ($this->quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }
    }
}
