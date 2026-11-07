<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;

/**
 * Layanan Penjadwalan & Verifikasi Panggilan Bangun Pagi Tamu (Wake-Up Call Service).
 */
final class HotelGuestWakeUpCallSchedulerService
{
    /**
     * @return array{call_id: string, room_number: string, scheduled_time: string, status: string}
     */
    public function scheduleWakeUpCall(string $roomNumber, CarbonImmutable $targetTime, string $specialNotes = ''): array
    {
        $callId = 'WUC-' . $roomNumber . '-' . $targetTime->format('Hi');

        return [
            'call_id' => $callId,
            'room_number' => $roomNumber,
            'scheduled_time' => $targetTime->toDateTimeString(),
            'status' => 'PENDING_PBX_DISPATCH',
        ];
    }
}
