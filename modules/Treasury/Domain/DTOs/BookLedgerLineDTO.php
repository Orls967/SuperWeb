<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

final class BookLedgerLineDTO
{
    public int $day;
    public string $reference;
    public string $date;

    public function __construct(
        public string $id,
        int|string $day = 1,
        public int $amount = 0,
        string $reference = '',
        public string $description = '',
        ?string $referenceNo = null,
        ?string $date = null
    ) {
        if ($date !== null) {
            $parsedDay = (int) date('j', strtotime($date));
            $this->day = $parsedDay > 0 ? $parsedDay : 1;
            $this->date = $date;
        } else {
            $this->day = is_numeric($day) ? (int) $day : 1;
            $dayStr = str_pad((string) $this->day, 2, '0', STR_PAD_LEFT);
            $this->date = "2026-10-{$dayStr}";
        }

        $this->reference = $referenceNo ?? $reference;
    }
}
