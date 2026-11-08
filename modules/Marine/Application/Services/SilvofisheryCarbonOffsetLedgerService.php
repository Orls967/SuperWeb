<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;

/**
 * Service pengimbangan karbon biru silvofishery dan sertifikasi ekspor netral karbon (Fase 171 Sub-item 171.3).
 */
class SilvofisheryCarbonOffsetLedgerService
{
    /**
     * Alokasikan kredit karbon mangrove (retirement / insetting) untuk mengimbangi emisi lot ekspor seafood.
     *
     * @return array{
     *     offset_certificate_code: string,
     *     lot_tracking_number: string,
     *     carbon_footprint_tonnes_co2e: float,
     *     credits_retired_tco2e: float,
     *     issuance_serial_number: string,
     *     remaining_active_credits_tco2e: float,
     *     offset_status: string,
     *     net_zero_verified: bool
     * }
     */
    public function retireCreditsForLotInsetting(
        int $exportLotId,
        int $creditIssuanceId,
        float $lotEmissionsTonnesCo2e
    ): array {
        $lot = AquacultureExportLot::findOrFail($exportLotId);
        $issuance = BlueCarbonCreditIssuance::findOrFail($creditIssuanceId);

        if ($lotEmissionsTonnesCo2e <= 0.0) {
            throw new DomainException('Emisi karbon lot ekspor harus lebih besar dari 0 tCO2e.');
        }

        $activeCredits = (float) $issuance->carbon_credits_tco2e;
        if ($activeCredits < $lotEmissionsTonnesCo2e) {
            throw new DomainException("Kredit karbon aktif tidak mencukupi (Tersedia: {$activeCredits} tCO2e, Dibutuhkan: {$lotEmissionsTonnesCo2e} tCO2e).");
        }

        $remaining = round($activeCredits - $lotEmissionsTonnesCo2e, 2);
        $issuance->update([
            'carbon_credits_tco2e' => $remaining,
            'credits_retired_tco2e' => round(((float) $issuance->credits_retired_tco2e) + $lotEmissionsTonnesCo2e, 2),
        ]);

        $certCode = 'OFFSET-CERT-'.strtoupper(substr(hash('sha256', $lot->lot_tracking_number.microtime()), 0, 10));

        return [
            'offset_certificate_code' => $certCode,
            'lot_tracking_number' => $lot->lot_tracking_number,
            'carbon_footprint_tonnes_co2e' => $lotEmissionsTonnesCo2e,
            'credits_retired_tco2e' => $lotEmissionsTonnesCo2e,
            'issuance_serial_number' => $issuance->serial_number,
            'remaining_active_credits_tco2e' => $remaining,
            'offset_status' => 'INSETTING_RETIRED_PERMANENTLY',
            'net_zero_verified' => true,
        ];
    }
}
