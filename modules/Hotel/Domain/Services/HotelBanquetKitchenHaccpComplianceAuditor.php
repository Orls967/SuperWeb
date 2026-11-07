<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;

class HotelBanquetKitchenHaccpComplianceAuditor
{
    /**
     * Memverifikasi audit titik kendali kritis higienitas pangan (HACCP Critical Control Point Audit) sebelum hidangan disajikan.
     *
     * @return array{contract_id: string, core_cooking_temp_celsius: float, is_haccp_compliant: bool, hygiene_inspector: string, audited_at: string, clearance_badge: string}
     */
    public function auditHaccpCompliance(
        string $contractId,
        float $proteinCoreCookingTemperatureCelsius,
        string $inspectorName = 'Quality Assurance Hygiene Officer'
    ): array {
        // Suhu inti pemasakan protein unggas & daging minimal 74°C untuk membunuh Salmonella/E. Coli
        $isCompliant = $proteinCoreCookingTemperatureCelsius >= 74.0;
        $badge = $isCompliant ? 'HACCP_SAFE_TO_SERVE' : 'FAIL_HOLD_RECOOK_DISCARD';

        return [
            'contract_id' => $contractId,
            'core_cooking_temp_celsius' => $proteinCoreCookingTemperatureCelsius,
            'is_haccp_compliant' => $isCompliant,
            'hygiene_inspector' => $inspectorName,
            'audited_at' => CarbonImmutable::now()->toIso8601String(),
            'clearance_badge' => $badge,
        ];
    }
}
