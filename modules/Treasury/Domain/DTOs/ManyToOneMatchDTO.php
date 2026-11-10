<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

final class ManyToOneMatchDTO
{
    public string $bankLineId;
    public array $bookLineIds;

    /**
     * @param  list<string>  $bookIds
     */
    public function __construct(
        public string $bankId,
        public array $bookIds,
        public int $bankAmount,
        public int $totalBookAmount
    ) {
        $this->bankLineId = $bankId;
        $this->bookLineIds = $bookIds;
    }
}
