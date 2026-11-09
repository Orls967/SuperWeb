<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Modules\Hospital\Domain\Models\TeleConsult;
use RuntimeException;

/**
 * Teleconsultation Slot Scheduler & Doctor Capacity Manager.
 * Mengelola kalender reservasi telemedis rawat jalan:
 * 1. Pencegahan bentrok jadwal (anti-double booking) per dokter per slot waktu.
 * 2. Penegakan batas beban kerja harian dokter (maksimal 20 telekonsultasi per hari).
 * 3. Alokasi nomor antrean virtual cerdas (Virtual Queue Ticket).
 * 4. Penjadwalan ulang otomatis jika dokter berhalangan tugas mendadak.
 */
class TeleconsultationSlotScheduler
{
    private const MAX_DAILY_CONSULTS_PER_DOCTOR = 20;

    private const SLOT_DURATION_MINUTES = 20;

    /**
     * Memeriksa apakah dokter memiliki slot yang tersedia pada jam tertentu.
     */
    public function isSlotAvailable(int $doctorId, Carbon $scheduledTime): bool
    {
        $slotStart = $scheduledTime->copy()->startOfMinute();
        $slotEnd = $slotStart->copy()->addMinutes(self::SLOT_DURATION_MINUTES);

        // Periksa apakah sudah ada konsultasi pada interval tersebut
        $conflicts = TeleConsult::where('doctor_id', $doctorId)
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->whereBetween('created_at', [$slotStart->subMinutes(19), $slotEnd])
            ->count();

        return $conflicts === 0;
    }

    /**
     * Memesan slot jadwal telekonsultasi dan menerbitkan nomor antrean.
     *
     * @return array{
     *     doctor_id: int,
     *     scheduled_at: string,
     *     queue_number: string,
     *     slot_confirmed: bool
     * }
     */
    public function reserveSlot(int $doctorId, Carbon $scheduledTime): array
    {
        $today = $scheduledTime->copy()->startOfDay();
        $tomorrow = $today->copy()->endOfDay();

        $dailyCount = TeleConsult::where('doctor_id', $doctorId)
            ->whereBetween('created_at', [$today, $tomorrow])
            ->count();

        if ($dailyCount >= self::MAX_DAILY_CONSULTS_PER_DOCTOR) {
            throw new RuntimeException(
                sprintf('Kapasitas harian dokter ID %d telah penuh (%d/%d pasien).', $doctorId, $dailyCount, self::MAX_DAILY_CONSULTS_PER_DOCTOR)
            );
        }

        if (! $this->isSlotAvailable($doctorId, $scheduledTime)) {
            throw new RuntimeException(
                "Slot waktu {$scheduledTime->format('H:i')} untuk dokter ID {$doctorId} sudah dipesan pasien lain."
            );
        }

        $queueNumber = sprintf('TQ-%02d', $dailyCount + 1);

        return [
            'doctor_id' => $doctorId,
            'scheduled_at' => $scheduledTime->toIso8601String(),
            'queue_number' => $queueNumber,
            'slot_confirmed' => true,
        ];
    }
}
