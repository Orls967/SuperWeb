<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Layanan Pembebanan Biaya Minibar, Laundry, & Fasilitas Kamar ke Folio Tamu.
 */
final class HotelMinibarAndLaundryPostingService
{
    /**
     * @return array{line_id: string, total_charged_idr: int, line_type: string}
     */
    public function postIncidentalCharge(
        string|int $folioId,
        string $incidentalType,
        int $baseAmountIdr,
        string $itemSummary
    ): array {
        $folio = HotelFolio::findOrFail((string) $folioId);

        // PBJT & Service Charge untuk incidental (10% + 5%)
        $serviceCharge = (int) round($baseAmountIdr * 0.05);
        $taxPbjt = (int) round(($baseAmountIdr + $serviceCharge) * 0.10);
        $totalAmount = $baseAmountIdr + $serviceCharge + $taxPbjt;

        $line = HotelFolioLine::create([
            'folio_id' => $folio->id,
            'business_date' => date('Y-m-d'),
            'line_type' => $incidentalType,
            'description' => $itemSummary,
            'amount_idr' => $totalAmount,
            'base_amount_idr' => $baseAmountIdr,
            'service_charge_idr' => $serviceCharge,
            'tax_pbjt_idr' => $taxPbjt,
        ]);

        return [
            'line_id' => (string) $line->id,
            'total_charged_idr' => $totalAmount,
            'line_type' => $incidentalType,
        ];
    }
}
