<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

final class ExactMatchDTO
{
    public string $bankLineId;

    public string $bookLineId;

    public function __construct(
        public string $bankId,
        public string $bookId,
        public int $amount,
        public string $reference
    ) {
        $this->bankLineId = $bankId;
        $this->bookLineId = $bookId;
    }
}
