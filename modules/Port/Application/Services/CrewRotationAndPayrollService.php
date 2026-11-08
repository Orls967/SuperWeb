<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Service Rotasi Awak Kapal, Kepatuhan MLC 2006 & Penggajian Maritim (Crewing Service).
 * Standar: STCW Regulation VIII/1 & Maritime Labour Convention (MLC) 2006 Regulation 2.3.
 */
final class CrewRotationAndPayrollService
{
    /**
     * Audit catatan jam istirahat pelaut untuk mencegah kelelahan di laut (Fatigue Prevention).
     * Aturan MLC 2006 / STCW:
     * - Istirahat minimum 10 jam dalam setiap periode 24 jam.
     * - Istirahat minimum 77 jam dalam setiap periode 7 hari.
     * - Periode istirahat tidak boleh dibagi lebih dari dua periode, salah satunya minimal 6 jam.
     *
     * @param array<int, array{date: string, rest_hours_24h: float, longest_period_hours: float}> $restLog7Days
     * @return array{
     *     is_compliant: bool,
     *     total_rest_7days_hours: float,
     *     deficiencies: array<int, string>,
     *     fatigue_risk_level: string
     * }
     */
    public function auditMlcRestHoursCompliance(array $restLog7Days): array
    {
        $deficiencies = [];
        $totalRestHours = 0.0;

        foreach ($restLog7Days as $day) {
            $hours = $day['rest_hours_24h'];
            $longest = $day['longest_period_hours'];
            $totalRestHours += $hours;

            if ($hours < 10.0) {
                $deficiencies[] = "Tanggal {$day['date']}: Total jam istirahat ({$hours} jam) kurang dari 10 jam.";
            }

            if ($longest < 6.0) {
                $deficiencies[] = "Tanggal {$day['date']}: Periode istirahat terpanjang ({$longest} jam) kurang dari 6 jam.";
            }
        }

        if ($totalRestHours < 77.0) {
            $deficiencies[] = "Total jam istirahat 7 hari ({$totalRestHours} jam) kurang dari standar minimum MLC (77 jam).";
        }

        $isCompliant = empty($deficiencies);
        $riskLevel = $isCompliant ? 'LOW' : ($totalRestHours < 60.0 ? 'CRITICAL' : 'ELEVATED');

        return [
            'is_compliant' => $isCompliant,
            'total_rest_7days_hours' => round($totalRestHours, 2),
            'deficiencies' => $deficiencies,
            'fatigue_risk_level' => $riskLevel,
        ];
    }

    /**
     * Catat proses rotasi pergantian awak kapal di pelabuhan (Sign-On / Sign-Off).
     */
    public function processCrewRotation(
        int $vesselId,
        string $crewMemberName,
        string $rank, // MASTER, CHIEF_ENGINEER, SECOND_OFFICER, BOSUN, AB
        string $seamansBookNumber,
        string $actionType, // SIGN_ON, SIGN_OFF
        string $portName,
        CarbonImmutable $effectiveDate,
        bool $medicalCertValid = true
    ): array {
        $vessel = FleetVessel::findOrFail($vesselId);

        if ($actionType === 'SIGN_ON' && ! $medicalCertValid) {
            throw new DomainException('Pelaut tidak dapat Sign-On tanpa sertifikat kesehatan maritim yang valid.');
        }

        return [
            'vessel_id' => $vessel->id,
            'vessel_name' => $vessel->vessel_name,
            'crew_name' => $crewMemberName,
            'rank' => $rank,
            'seamans_book' => $seamansBookNumber,
            'action' => $actionType,
            'port' => $portName,
            'effective_date' => $effectiveDate->toDateString(),
            'status' => 'PROCESSED',
        ];
    }
}
