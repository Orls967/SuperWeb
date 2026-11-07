<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelTimeshareDeedOwnershipRegistryService
{
    /**
     * Memverifikasi integritas total kepemilikan 100 lembar token fraksional pada unit vila.
     *
     * @return array{timeshare_unit_id: string, unit_code: string, total_token_shares: int, registered_investor_shares: int, unallocated_shares: int, is_fully_syndicated: bool, registry_status: string}
     */
    public function auditUnitTokenCapTable(string $timeshareUnitId): array
    {
        $unit = TimeshareUnit::findOrFail($timeshareUnitId);
        $investors = TimeshareInvestor::where('timeshare_unit_id', $timeshareUnitId)->get();

        $registeredShares = (int) $investors->sum('token_shares');
        $totalAllowed = (int) ($unit->total_token_shares ?? 100);

        if ($registeredShares > $totalAllowed) {
            throw new InvalidArgumentException("Integritas kepemilikan terlanggar: Total lembar token terdaftar ({$registeredShares}) melebihi kuota ({$totalAllowed}).");
        }

        $unallocated = max(0, $totalAllowed - $registeredShares);
        $isFullySyndicated = ($unallocated === 0);

        return [
            'timeshare_unit_id' => $unit->id,
            'unit_code' => $unit->unit_code,
            'total_token_shares' => $totalAllowed,
            'registered_investor_shares' => $registeredShares,
            'unallocated_shares' => $unallocated,
            'is_fully_syndicated' => $isFullySyndicated,
            'registry_status' => $isFullySyndicated ? 'CAP_TABLE_CLOSED' : 'OPEN_FOR_SYNDICATION',
        ];
    }
}
