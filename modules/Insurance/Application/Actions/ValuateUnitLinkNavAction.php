<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Modules\Insurance\Domain\Models\UnitLinkHolding;
use Modules\Insurance\Domain\Models\UnitLinkNavHistory;

final class ValuateUnitLinkNavAction
{
    /**
     * Memperbarui NAV harian per unit untuk suatu fund dan merevaluasi seluruh unit holdings nasabah.
     */
    public function execute(
        string $fundCode,
        string $valuationDate,
        int $newNavPerUnitIdr,
    ): array {
        if ($newNavPerUnitIdr <= 0) {
            throw new InvalidArgumentException('Nilai NAV per unit harus bernilai positif.');
        }

        return DB::transaction(function () use ($fundCode, $valuationDate, $newNavPerUnitIdr) {
            $fund = UnitLinkFund::where('fund_code', $fundCode)->lockForUpdate()->firstOrFail();

            $totalUnits = $fund->total_units;
            $newAumIdr = (int) intdiv($totalUnits * $newNavPerUnitIdr, 1000);

            $fund->update([
                'current_nav_per_unit_idr' => $newNavPerUnitIdr,
                'total_aum_idr' => $newAumIdr,
            ]);

            UnitLinkNavHistory::updateOrCreate(
                ['fund_id' => $fund->id, 'valuation_date' => $valuationDate],
                [
                    'nav_per_unit_idr' => $newNavPerUnitIdr,
                    'total_aum_idr' => $newAumIdr,
                    'total_units' => $totalUnits,
                ]
            );

            // Update mark-to-market valuation semua holdings di fund ini
            $holdings = UnitLinkHolding::where('fund_id', $fund->id)->get();
            $updatedHoldingsCount = 0;
            foreach ($holdings as $holding) {
                $currentValuation = (int) intdiv($holding->units_held * $newNavPerUnitIdr, 1000);
                $holding->update(['current_valuation_idr' => $currentValuation]);
                $updatedHoldingsCount++;
            }

            return [
                'fund_code' => $fundCode,
                'valuation_date' => $valuationDate,
                'nav_per_unit_idr' => $newNavPerUnitIdr,
                'total_aum_idr' => $newAumIdr,
                'holdings_revalued' => $updatedHoldingsCount,
            ];
        });
    }
}

