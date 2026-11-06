<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Application\DTOs\Nfpa110ReadinessReportDTO;
use Modules\Egy\Domain\Models\GeneratorLog;

/**
 * Layanan Audit Kesiapan Sistem Daya Darurat (NFPA 110 & Kemenkes).
 * Memverifikasi otonomi bahan bakar 72 jam, waktu transfer ATS <= 10 detik,
 * dan kepatuhan uji beban bulanan untuk Rumah Sakit dan Data Center.
 */
final class EmergencyPowerReadinessAuditService
{
    /**
     * Lakukan audit kesiapan daya darurat untuk satu lokasi site.
     *
     * @param  string  $siteName  Nama fasilitas (misal 'RS Pekerja Cikarang')
     * @param  int  $connectedCriticalLoadKw  Beban darurat esensial (kW)
     * @param  int  $availableFuelLiters  Cadangan solar di tangki harian & tangki timbun (Liter)
     * @param  int  $measuredAtsTransferTimeSeconds  Waktu transfer otomatis ATS terukur
     */
    public function auditEmergencyReadiness(
        string $siteName,
        int $connectedCriticalLoadKw,
        int $availableFuelLiters,
        int $measuredAtsTransferTimeSeconds = 8
    ): Nfpa110ReadinessReportDTO {
        // Konsumsi spesifik diesel tipikal: ~0.25 Liter per kWh (250 liter per 1000 kWh)
        $fuelBurnRateLitersPerHour = max(1, (int) intdiv($connectedCriticalLoadKw * 250, 1000));

        // Kebutuhan otonomi 72 jam (NFPA 110 Class 72 untuk Level 1 Life-Safety)
        $required72HourFuelLiters = $fuelBurnRateLitersPerHour * 72;

        // Otonomi jam yang tersedia saat ini
        $fuelAutonomyHours = (int) intdiv($availableFuelLiters, $fuelBurnRateLitersPerHour);

        // Ambil riwayat log uji beban genset 30 hari terakhir
        $recentLoadTests = GeneratorLog::where('site_name', $siteName)
            ->where('run_type', 'weekly_load_test')
            ->where('is_passed', true)
            ->where('start_time', '>=', now()->subDays(30))
            ->count();

        $isWeeklyLoadTestCompliant = $recentLoadTests >= 4; // Minimal 4 kali tes per bulan
        $isAtsCompliant = $measuredAtsTransferTimeSeconds <= 10; // NFPA 110 mensyaratkan transfer <= 10 detik
        $isFuelCompliant = $availableFuelLiters >= $required72HourFuelLiters;

        $isOverallCompliant = $isWeeklyLoadTestCompliant && $isAtsCompliant && $isFuelCompliant;

        $checklist = [
            [
                'item' => 'NFPA 110 Type 10 ATS Transfer Time',
                'target' => '<= 10 detik',
                'measured' => "{$measuredAtsTransferTimeSeconds} detik",
                'status' => $isAtsCompliant ? 'PASS' : 'FAIL',
            ],
            [
                'item' => 'NFPA 110 Class 72 Fuel Autonomy',
                'target' => '>= 72 jam ('.number_format($required72HourFuelLiters).' L)',
                'measured' => "{$fuelAutonomyHours} jam (".number_format($availableFuelLiters).' L)',
                'status' => $isFuelCompliant ? 'PASS' : 'FAIL',
            ],
            [
                'item' => 'Monthly Load Test Frequency',
                'target' => '>= 4 kali uji beban 30% per bulan',
                'measured' => "{$recentLoadTests} kali",
                'status' => $isWeeklyLoadTestCompliant ? 'PASS' : 'FAIL',
            ],
        ];

        $hashPayload = sprintf(
            'NFPA-AUDIT|SITE:%s|CLASS:72|ATS:%d|FUEL_AVAIL:%d|REQ:%d|HOURS:%d|TESTS:%d|COMPLIANT:%d',
            $siteName,
            $measuredAtsTransferTimeSeconds,
            $availableFuelLiters,
            $required72HourFuelLiters,
            $fuelAutonomyHours,
            $recentLoadTests,
            $isOverallCompliant ? 1 : 0
        );
        $complianceHash = hash('sha256', $hashPayload);

        return new Nfpa110ReadinessReportDTO(
            siteName: $siteName,
            emergencyPowerSupplySystemClass: 'Class 72',
            level: 'Level 1 Life Safety',
            atsTransferTimeSeconds: $measuredAtsTransferTimeSeconds,
            availableOnsiteFuelLiters: $availableFuelLiters,
            required72HourFuelLiters: $required72HourFuelLiters,
            fuelAutonomyHours: $fuelAutonomyHours,
            weeklyLoadTestStatus: $isWeeklyLoadTestCompliant ? 'COMPLIANT' : 'NON_COMPLIANT',
            overallComplianceStatus: $isOverallCompliant ? 'COMPLIANT' : 'NON_COMPLIANT',
            facilityChecklist: $checklist,
            complianceHash: $complianceHash
        );
    }
}
