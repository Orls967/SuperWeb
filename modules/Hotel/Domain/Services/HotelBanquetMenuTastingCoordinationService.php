<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\MiceContract;

class HotelBanquetMenuTastingCoordinationService
{
    /**
     * Mengatur jadwal sesi uji coba menu (Food Tasting Session) bagi calon pengantin atau panitia MICE korporat.
     *
     * @return array{contract_id: string, tasting_session_id: string, scheduled_date: string, attendees_count: int, chef_in_charge: string, is_complimentary: bool, tasting_status: string}
     */
    public function scheduleFoodTasting(
        string $contractId,
        string $scheduledDate,
        int $attendeesCount = 6,
        string $chefName = 'Executive Chef Wayan Sudarma'
    ): array {
        $contract = MiceContract::findOrFail($contractId);
        $contractValue = (int) ($contract->contract_value_idr ?? 0);

        if ($attendeesCount <= 0) {
            throw new InvalidArgumentException('Jumlah peserta food tasting harus lebih dari nol.');
        }

        // Complimentary tasting bila nilai kontrak >= Rp 100 Juta untuk maksimal 6 orang
        $isComplimentary = ($contractValue >= 100000000) && ($attendeesCount <= 6);
        $tastingSessionId = 'TST-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        return [
            'contract_id' => $contract->id,
            'tasting_session_id' => $tastingSessionId,
            'scheduled_date' => $scheduledDate,
            'attendees_count' => $attendeesCount,
            'chef_in_charge' => $chefName,
            'is_complimentary' => $isComplimentary,
            'tasting_status' => 'TASTING_CONFIRMED',
        ];
    }
}
