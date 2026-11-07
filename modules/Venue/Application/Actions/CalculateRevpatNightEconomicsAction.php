<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\TableRevpatMetric;

final class CalculateRevpatNightEconomicsAction extends BaseAction
{
    public function execute(
        string $tableCode,
        string $metricDate,
        string $zoneCode,
        int $availableHours,
        int $occupiedHours,
        int $foodRevenueIdr,
        int $beverageRevenueIdr,
        int $minimumSpendShortfallIdr = 0
    ): TableRevpatMetric {
        return DB::transaction(function () use (
            $tableCode,
            $metricDate,
            $zoneCode,
            $availableHours,
            $occupiedHours,
            $foodRevenueIdr,
            $beverageRevenueIdr,
            $minimumSpendShortfallIdr
        ) {
            if ($availableHours <= 0) {
                throw new InvalidArgumentException('Jam ketersediaan meja harus lebih besar dari 0.');
            }

            $totalRevenueIdr = $foodRevenueIdr + $beverageRevenueIdr + $minimumSpendShortfallIdr;
            $revpatIdr = (int) intdiv($totalRevenueIdr, $availableHours);

            /** @var TableRevpatMetric $metric */
            $metric = TableRevpatMetric::query()->updateOrCreate(
                [
                    'table_code' => $tableCode,
                    'metric_date' => Carbon::parse($metricDate)->toDateString(),
                ],
                [
                    'zone_code' => $zoneCode,
                    'available_hours' => $availableHours,
                    'occupied_hours' => $occupiedHours,
                    'food_revenue_idr' => $foodRevenueIdr,
                    'beverage_revenue_idr' => $beverageRevenueIdr,
                    'minimum_spend_shortfall_idr' => $minimumSpendShortfallIdr,
                    'total_revenue_idr' => $totalRevenueIdr,
                    'revpat_idr' => $revpatIdr,
                ]
            );

            return $metric;
        });
    }
}
