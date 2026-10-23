<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use Modules\Shared\Application\BaseAction;

class MintTimeshareFractionalVillaPointsAction extends BaseAction
{
    /**
     * Mint timeshare fractional villa points unit for syndication investors.
     *
     * @return array{unit: TimeshareUnit, unit_code: string, total_shares: int, daily_rate_idr: int}
     */
    public function handle(
        string $propertyId,
        string $unitCode,
        string $unitName,
        int $dailyRentalRateIdr,
        int $totalTokenShares = 100
    ): array {
        return DB::transaction(function () use ($propertyId, $unitCode, $unitName, $dailyRentalRateIdr, $totalTokenShares) {
            $unit = TimeshareUnit::create([
                'id' => (string) Str::uuid(),
                'property_id' => $propertyId,
                'unit_code' => $unitCode,
                'unit_name' => $unitName,
                'total_token_shares' => $totalTokenShares,
                'daily_rental_rate_idr' => $dailyRentalRateIdr,
            ]);

            $this->audit(
                action: 'hotel.timeshare.minted',
                auditable: $unit,
                oldValues: null,
                newValues: [
                    'property_id' => $propertyId,
                    'unit_code' => $unitCode,
                    'total_token_shares' => $totalTokenShares,
                    'daily_rate_idr' => $dailyRentalRateIdr,
                ],
                correlationId: "htl_ts_{$unitCode}",
                impactType: 'timeshare_syndication'
            );

            return [
                'unit' => $unit,
                'unit_code' => $unitCode,
                'total_shares' => $totalTokenShares,
                'daily_rate_idr' => $dailyRentalRateIdr,
            ];
        });
    }
}
