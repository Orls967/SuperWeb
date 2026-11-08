<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

use Illuminate\Support\Carbon;

class AviationCrewRosterComplianceService
{
    /**
     * Periksa apakah perpanjangan jam terbang kru akibat disrupsi masih sah menurut CASR Part 121 Subpart Q.
     *
     * @param  string  $crewId  Identitas kru pilot/cabin crew
     * @param  string  $dutyStartTime  Waktu mulai bertugas (report time)
     * @param  int  $plannedSectors  Jumlah sektor penerbangan yang diterbangkan
     * @param  int  $anticipatedDelayMinutes  Estimasi penambahan waktu bertugas akibat keterlambatan
     * @return array{
     *     crew_id: string,
     *     max_allowed_fdp_hours: float,
     *     projected_fdp_hours: float,
     *     is_compliant: bool,
     *     rest_hours_required: float,
     *     violation_reason: ?string
     * }
     */
    public function evaluateDutyExtension(
        string $crewId,
        string $dutyStartTime,
        int $plannedSectors,
        int $anticipatedDelayMinutes
    ): array {
        $startTime = Carbon::parse($dutyStartTime);
        $startHour = (int) $startTime->format('H');

        // Batas maksimum FDP (Flight Duty Period) dua pilot berdasarkan waktu mulai dan jumlah sektor
        $baseMaxFdp = match (true) {
            $startHour >= 6 && $startHour <= 13 => match ($plannedSectors) {
                1, 2 => 13.0,
                3 => 12.5,
                4 => 12.0,
                default => 11.5,
            },
            $startHour >= 14 && $startHour <= 17 => match ($plannedSectors) {
                1, 2 => 12.0,
                3 => 11.5,
                default => 11.0,
            },
            default => 10.0, // Waktu malam / Window of Circadian Low (WOCL)
        };

        // Kebijakan diskresi kapten (Commander Discretion) mengizinkan perpanjangan maksimal 2 jam
        $maxWithDiscretion = $baseMaxFdp + 2.0;

        // Hitung perkiraan jam bertugas aktual
        $elapsedHours = (float) $startTime->diffInMinutes(now()) / 60.0;
        $projectedHours = $elapsedHours + ($anticipatedDelayMinutes / 60.0);

        $compliant = $projectedHours <= $maxWithDiscretion;
        $violationReason = null;

        if (! $compliant) {
            $violationReason = sprintf(
                'Perpanjangan FDP (%.2f jam) melampaui batas maksimum legal CASR 121 termasuk diskresi kapten (%.2f jam). Kru wajib diganti (Crew Time Out).',
                $projectedHours,
                $maxWithDiscretion
            );
        }

        // Waktu istirahat wajib minimal setara dengan durasi tugas sebelumnya atau minimal 12 jam
        $restRequired = max(12.0, $projectedHours);

        return [
            'crew_id' => $crewId,
            'max_allowed_fdp_hours' => $maxWithDiscretion,
            'projected_fdp_hours' => round($projectedHours, 2),
            'is_compliant' => $compliant,
            'rest_hours_required' => round($restRequired, 2),
            'violation_reason' => $violationReason,
        ];
    }
}
