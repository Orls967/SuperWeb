<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\ViewModels;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Aviation\Domain\Models\AviationFlight;

class YieldManagementViewModel
{
    /**
     * @param  LengthAwarePaginator<AviationFlight>  $flights
     * @param  array<string, mixed>  $summaryMetrics
     */
    public function __construct(
        public LengthAwarePaginator $flights,
        public array $summaryMetrics = []
    ) {}

    public function averageLoadFactor(): float
    {
        return (float) ($this->summaryMetrics['avg_load_factor'] ?? 0.0);
    }

    public function totalRevenueMinorUnit(): int
    {
        return (int) ($this->summaryMetrics['total_revenue_minor_unit'] ?? 0);
    }

    public function activeBucketsCount(): int
    {
        return (int) ($this->summaryMetrics['active_buckets'] ?? 0);
    }
}
