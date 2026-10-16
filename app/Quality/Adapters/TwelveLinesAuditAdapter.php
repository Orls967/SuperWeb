<?php

declare(strict_types=1);

namespace App\Quality\Adapters;

use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\TwelveLinesAuditPort;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Venue\Domain\Models\EntertainmentVenue;

final class TwelveLinesAuditAdapter implements TwelveLinesAuditPort
{
    public function getVenueCount(): int
    {
        return EntertainmentVenue::count();
    }

    public function getHotelCount(): int
    {
        return HotelProperty::count();
    }

    public function getMiningCount(): int
    {
        return MiningSite::count();
    }

    public function getNegativeAssetDiscrepancies(): int
    {
        return LedgerAccount::where('kind', 'asset')
            ->where('allow_negative', false)
            ->whereRaw('CAST(cached_balance AS NUMERIC) < 0')
            ->count();
    }
}
