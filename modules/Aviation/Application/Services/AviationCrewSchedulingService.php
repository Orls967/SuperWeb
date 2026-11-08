<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Aviation\Domain\Models\AviationFlight;

class AviationCrewSchedulingService
{
    /**
     * Verifikasi kepatuhan batas waktu terbang awak pesawat (Flight Time Limitations / FTL) sesuai CASR 121.471.
     *
     * @param int $flightId
     * @param array{
     *     captain_party_id: int,
     *     first_officer_party_id: int,
     *     duty_start_time: string,
     *     cumulative_flight_hours_28_days: int,
     *     last_rest_period_hours: int,
     *     is_augmented_crew?: bool
     * } $crewPayload
     * @return array{
     *     compliant: bool,
     *     flight_duty_period_minutes: int,
     *     max_allowed_duty_minutes: int,
     *     violations: array<int, string>,
     *     recommendations: array<int, string>
     * }
     */
    public function verifyFlightTimeLimitations(int $flightId, array $crewPayload): array
    {
        $flight = AviationFlight::with(['route'])->findOrFail($flightId);
        $flightDurationMinutes = (int) ($flight->route->scheduled_duration_minutes ?? 120);

        $dutyStart = Carbon::parse($crewPayload['duty_start_time']);
        $departure = Carbon::parse($flight->departure_time);
        $arrival = Carbon::parse($flight->arrival_time);

        // Flight Duty Period (FDP): Dari lapor tugas (H-60 mnt) hingga mesin mati (on-chocks +15 mnt)
        $dutyEnd = $arrival->copy()->addMinutes(15);
        $fdpMinutes = max(0, (int) $dutyStart->diffInMinutes($dutyEnd));

        $violations = [];
        $recommendations = [];

        // 1. Batas Istirahat Minimum (Rest Period): Wajib >= 9 jam atau setara durasi tugas sebelumnya
        if ($crewPayload['last_rest_period_hours'] < 9) {
            $violations[] = "Waktu istirahat kru sebelumnya ({$crewPayload['last_rest_period_hours']} jam) kurang dari batas minimum 9 jam.";
        }

        // 2. Batas FDP Harian: Standar 2 pilot = 13 jam (780 mnt) tanpa augmentasi
        $isAugmented = (bool) ($crewPayload['is_augmented_crew'] ?? false);
        $maxAllowedFdpMinutes = $isAugmented ? 960 : 780; // 16 jam dengan kru augmentasi (3-4 pilot)

        if ($fdpMinutes > $maxAllowedFdpMinutes) {
            $violations[] = "Durasi tugas penerbangan (FDP: {$fdpMinutes} menit) melampaui batas legal harian ({$maxAllowedFdpMinutes} menit).";
            $recommendations[] = 'Tugaskan kru tambahan (augmented crew) dengan fasilitas istirahat kelas 1 atau jadwalkan pergantian kru transit.';
        }

        // 3. Batas Jam Terbang Kumulatif 28 Hari Kalender (Maksimal 100 jam = 6.000 menit)
        $scheduledHours = (int) ceil($flightDurationMinutes / 60);
        $projected28Days = $crewPayload['cumulative_flight_hours_28_days'] + $scheduledHours;

        if ($projected28Days > 100) {
            $violations[] = "Akumulasi jam terbang 28 hari ({$projected28Days} jam) melampaui plafon keselamatan CASR 121 (100 jam).";
        } elseif ($projected28Days >= 90) {
            $recommendations[] = "Peringatan kelelahan: Akumulasi jam terbang telah mencapai {$projected28Days} jam (mendekati batas 100 jam).";
        }

        // 4. Jendela Circadian Low (WOCL): Tugas antara 02:00 - 05:59 waktu lokal
        $departureHour = (int) $departure->format('H');
        if ($departureHour >= 2 && $departureHour <= 5) {
            $recommendations[] = 'Penerbangan beroperasi di dalam Jendela Circadian Rendah (WOCL). Wajibkan verifikasi kesiagaan ganda sebelum pelepasan.';
        }

        return [
            'compliant' => empty($violations),
            'flight_duty_period_minutes' => $fdpMinutes,
            'max_allowed_duty_minutes' => $maxAllowedFdpMinutes,
            'violations' => $violations,
            'recommendations' => $recommendations,
        ];
    }
}

