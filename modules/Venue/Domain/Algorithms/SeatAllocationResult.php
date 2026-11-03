<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

class SeatAllocationResult
{
    /**
     * @param  list<array{id: int, section_code: string, row_label: string, seat_number: int}>  $seats
     */
    public function __construct(
        public array $seats,
        public ?SeatAllocationCertificate $certificate = null,
    ) {}

    public function isSuccess(): bool
    {
        return ! empty($this->seats);
    }
}
