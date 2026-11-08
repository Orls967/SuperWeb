<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\ViewModels;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;

class IrropsDashboardViewModel
{
    /**
     * @param  LengthAwarePaginator<AviationDisruptionCase>  $cases
     * @param  array<string, int>  $statistics
     */
    public function __construct(
        public LengthAwarePaginator $cases,
        public array $statistics = []
    ) {}

    public function activeCasesCount(): int
    {
        return $this->statistics['active_cases'] ?? 0;
    }

    public function impactedPassengersCount(): int
    {
        return $this->statistics['impacted_passengers'] ?? 0;
    }

    public function vouchersIssuedCount(): int
    {
        return $this->statistics['vouchers_issued'] ?? 0;
    }

    public function hotelRoomsBookedCount(): int
    {
        return $this->statistics['hotel_rooms'] ?? 0;
    }
}
