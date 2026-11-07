<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlTimeshareSchedule;

class HotelTimeshareSecondaryExchangePool
{
    /**
     * Memungkinkan dua pemilik timeshare melakukan swap (pertukaran minggu alokasi)
     * dengan kalkulasi penyesuaian biaya musiman (peak vs off-peak).
     *
     * @return array{swap_id: string, requester_schedule_id: string, target_schedule_id: string, balancing_fee_idr: int, status: string, swapped_at: string}
     */
    public function executeScheduleSwap(
        string $requesterScheduleId,
        string $targetScheduleId,
        int $standardBalancingRateIdr = 500000
    ): array {
        $reqSchedule = HtlTimeshareSchedule::findOrFail($requesterScheduleId);
        $tgtSchedule = HtlTimeshareSchedule::findOrFail($targetScheduleId);

        if ($reqSchedule->status !== 'OWNER_OCCUPIED' || $tgtSchedule->status !== 'OWNER_OCCUPIED') {
            throw new InvalidArgumentException('Hanya minggu yang berstatus OWNER_OCCUPIED yang dapat ditukar.');
        }

        // Kalkulasi fee jika menukar musim reguler ke peak
        $balancingFee = 0;
        if ($reqSchedule->season !== 'PEAK' && $tgtSchedule->season === 'PEAK') {
            $balancingFee = $standardBalancingRateIdr;
        }

        // Pertukaran owner_reference antar dua jadwal
        $tempOwner = $reqSchedule->owner_reference;
        $reqSchedule->owner_reference = $tgtSchedule->owner_reference;
        $tgtSchedule->owner_reference = $tempOwner;

        $reqSchedule->save();
        $tgtSchedule->save();

        return [
            'swap_id' => 'SWAP-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 8)),
            'requester_schedule_id' => $reqSchedule->id,
            'target_schedule_id' => $tgtSchedule->id,
            'balancing_fee_idr' => $balancingFee,
            'status' => 'COMPLETED',
            'swapped_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
