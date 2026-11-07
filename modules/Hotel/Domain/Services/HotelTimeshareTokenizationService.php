<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;

/**
 * Layanan Domain: Tokenisasi RWA Unit Villa & Bursa Pertukaran Hak Tinggal (Timeshare).
 */
final class HotelTimeshareTokenizationService
{
    /**
     * @return array{unit_code: string, total_token_shares: int, circulating_shares: int, available_for_syndication: int}
     */
    public function getUnitFractionalStatus(string $unitId): array
    {
        $unit = TimeshareUnit::findOrFail($unitId);
        $totalInvestorsShares = (int) TimeshareInvestor::where('timeshare_unit_id', $unit->id)->sum('token_shares');

        $maxTokens = (int) $unit->total_token_shares;
        $available = max(0, $maxTokens - $totalInvestorsShares);

        return [
            'unit_code' => $unit->unit_code,
            'total_token_shares' => $maxTokens,
            'circulating_shares' => $totalInvestorsShares,
            'available_for_syndication' => $available,
        ];
    }
}
