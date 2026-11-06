<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Laporan Kesiapan Sistem Daya Darurat (NFPA 110 & Kemenkes Standar Rumah Sakit/Data Center).
 */
final class Nfpa110ReadinessReportDTO
{
    /**
     * @param  list<array<string, mixed>>  $facilityChecklist
     */
    public function __construct(
        public readonly string $siteName,
        public readonly string $emergencyPowerSupplySystemClass, // Class 72 (72 jam otonomi)
        public readonly string $level, // Level 1 (Life Safety / ICU / Bedah)
        public readonly int $atsTransferTimeSeconds, // Harus <= 10 detik
        public readonly int $availableOnsiteFuelLiters,
        public readonly int $required72HourFuelLiters,
        public readonly int $fuelAutonomyHours,
        public readonly string $weeklyLoadTestStatus,
        public readonly string $overallComplianceStatus,
        public readonly array $facilityChecklist,
        public readonly string $complianceHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'site_name' => $this->siteName,
            'epss_class' => $this->emergencyPowerSupplySystemClass,
            'level' => $this->level,
            'ats_transfer_time_seconds' => $this->atsTransferTimeSeconds,
            'available_fuel_liters' => $this->availableOnsiteFuelLiters,
            'required_72h_fuel_liters' => $this->required72HourFuelLiters,
            'fuel_autonomy_hours' => $this->fuelAutonomyHours,
            'weekly_test_status' => $this->weeklyLoadTestStatus,
            'overall_status' => $this->overallComplianceStatus,
            'checklist' => $this->facilityChecklist,
            'compliance_hash' => $this->complianceHash,
        ];
    }
}
