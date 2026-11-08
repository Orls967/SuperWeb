<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;

class CodeshareClearingCsvExporter extends BaseCsvExporter
{
    /**
     * Ekspor rekapitulasi data kliring codeshare antar-maskapai.
     */
    public function exportAgreementSummary(int $agreementId): string
    {
        $agreement = AviationCodeshareAgreement::with(['operatingAirline', 'marketingAirline', 'codeshareFlights.operatingFlight'])
            ->findOrFail($agreementId);

        $headers = [
            'Kode Perjanjian',
            'Operating Carrier',
            'Marketing Carrier',
            'Tipe Perjanjian',
            'Bps Operating',
            'Status Perjanjian',
            'Nomor Marketing',
            'Nomor Fisik Operating',
            'Alokasi Kursi Block',
        ];

        $rows = [$headers];

        foreach ($agreement->codeshareFlights as $csFlight) {
            $rows[] = [
                $agreement->agreement_code,
                $agreement->operatingAirline->name ?? 'N/A',
                $agreement->marketingAirline->name ?? 'N/A',
                $agreement->agreement_type,
                (string) $agreement->revenue_split_bps,
                $agreement->status,
                $csFlight->marketing_flight_number,
                $csFlight->operatingFlight->flight_number ?? 'N/A',
                (string) $csFlight->allotted_seats,
            ];
        }

        return $this->generateCsvString($rows);
    }
}
