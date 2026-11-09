<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * HospitalBedTurnaroundOptimizationService
 *
 * Mengoptimalkan perputaran tempat tidur rawat inap (Bed Turnaround Time / BTT)
 * dari kepulangan pasien (discharge) -> desinfeksi sanitasi tata graha (environmental cleaning) -> kesiapan admisi pasien baru.
 * Memastikan Bed Occupancy Rate (BOR) dan Turn Around Interval (TOI) sesuai indikator Barber-Johnson Kemenkes.
 */
class HospitalBedTurnaroundOptimizationService
{
    public const BED_STATUS_DISCHARGED_DIRTY = 'BED_DIRTY_PENDING_CLEANING';
    public const BED_STATUS_TERMINAL_CLEANING = 'BED_TERMINAL_CLEANING_ACTIVE';
    public const BED_STATUS_INSPECTED_READY = 'BED_INSPECTED_READY_FOR_ADMISSION';
    public const BED_STATUS_BLOCKED_MAINTENANCE = 'BED_BLOCKED_PHYSICAL_MAINTENANCE';

    /**
     * Target waktu maksimum turnaround tempat tidur standar Kemenkes (120 menit = 2 jam).
     */
    public const MAX_STANDARD_TURNAROUND_MINUTES = 120;

    /**
     * Evaluasi efisiensi turnaround tempat tidur bangsal.
     *
     * @param string $bedCode Kode fisik tempat tidur (misal: 'BED-WARD-3A-102')
     * @param string $cleaningType Jenis pembersihan ('ROUTINE_DISCHARGE', 'ISOLATION_TERMINAL_UV', 'INFECTIOUS_AIRBORNE')
     * @param DateTimeImmutable $dischargeTime Waktu pasien pulang
     * @param DateTimeImmutable $cleaningStartTime Waktu tim tata graha mulai sanitasi
     * @param DateTimeImmutable $readyTime Waktu perawat kepala memvalidasi bed siap pakai
     * @param bool $passedAtpBioluminescenceCheck Apakah lulus uji kebersihan swab ATP bioluminesensi (< 100 RLU)
     * @return array{
     *     bed_code: string,
     *     cleaning_type: string,
     *     turnaround_duration_minutes: int,
     *     is_within_benchmark_time: bool,
     *     cleanliness_verified: bool,
     *     final_bed_status: string,
     *     efficiency_rating: string,
     *     corrective_action: ?string
     * }
     */
    public function evaluateBedTurnaround(
        string $bedCode,
        string $cleaningType,
        DateTimeImmutable $dischargeTime,
        DateTimeImmutable $cleaningStartTime,
        DateTimeImmutable $readyTime,
        bool $passedAtpBioluminescenceCheck
    ): array {
        if (empty($bedCode)) {
            throw new InvalidArgumentException('Kode bed tidak boleh kosong');
        }

        $totalElapsedSeconds = $readyTime->getTimestamp() - $dischargeTime->getTimestamp();
        $totalMinutes = max(0, (int) round($totalElapsedSeconds / 60));

        $targetMinutes = self::MAX_STANDARD_TURNAROUND_MINUTES;
        if ($cleaningType === 'ISOLATION_TERMINAL_UV' || $cleaningType === 'INFECTIOUS_AIRBORNE') {
            $targetMinutes = 180; // Diberikan toleransi 3 jam untuk fogging hidrogen peroksida / UV-C
        }

        $isWithinTime = ($totalMinutes <= $targetMinutes);
        $finalStatus = self::BED_STATUS_INSPECTED_READY;
        $correctiveAction = null;

        if (!$passedAtpBioluminescenceCheck) {
            $finalStatus = self::BED_STATUS_DISCHARGED_DIRTY;
            $correctiveAction = 'Uji ATP bioluminesensi menunjukkan residu kontaminasi biologis (> 100 RLU). Wajib dilakukan pembersihan ulang!';
        } elseif (!$isWithinTime) {
            $correctiveAction = sprintf('Waktu turnaround (%d menit) melebihi batas target (%d menit). Evaluasi respons tim housekeeping.', $totalMinutes, $targetMinutes);
        }

        $rating = 'EXCELLENT';
        if ($totalMinutes > $targetMinutes * 1.5 || !$passedAtpBioluminescenceCheck) {
            $rating = 'POOR_DELAYED';
        } elseif (!$isWithinTime) {
            $rating = 'ACCEPTABLE_MODERATE';
        }

        return [
            'bed_code' => $bedCode,
            'cleaning_type' => $cleaningType,
            'turnaround_duration_minutes' => $totalMinutes,
            'is_within_benchmark_time' => $isWithinTime,
            'cleanliness_verified' => $passedAtpBioluminescenceCheck,
            'final_bed_status' => $finalStatus,
            'efficiency_rating' => $rating,
            'corrective_action' => $correctiveAction,
        ];
    }
}
