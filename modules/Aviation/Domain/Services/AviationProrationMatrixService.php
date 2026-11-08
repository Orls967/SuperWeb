<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

use Modules\Aviation\Domain\Algorithms\MultilateralProrateAgreementCalculator;

class AviationProrationMatrixService
{
    public function __construct(
        protected MultilateralProrateAgreementCalculator $calculator
    ) {}

    /**
     * Hitung matriks pembagian penerimaan tiket untuk jadwal perjalanan multi-kota.
     *
     * @param  array<int, array{carrier: string, origin: string, destination: string, tpm: int, area?: string}>  $itinerary
     * @return array{
     *     total_fare_minor_unit: int,
     *     segments: array<int, array{carrier: string, sector: string, tpm: int, prorate_factor: float, allocated_fare_minor_unit: int}>,
     *     interline_service_fee_minor_unit: int
     * }
     */
    public function calculateItineraryProration(int $totalTicketFareMinorUnit, array $itinerary): array
    {
        $inputSegments = [];
        foreach ($itinerary as $idx => $leg) {
            $inputSegments[] = [
                'segment_id' => $idx,
                'tpm' => $leg['tpm'],
                'area' => $leg['area'] ?? 'TC3',
            ];
        }

        $distributed = $this->calculator->distributeMultiSectorFare($totalTicketFareMinorUnit, $inputSegments);

        $outSegments = [];
        $totalIsc = 0;

        foreach ($distributed as $d) {
            $origLeg = $itinerary[$d['segment_id']];
            $fareShare = $d['allocated_fare_minor_unit'];
            $isc = (int) round($fareShare * 0.09); // ISC 9%
            $totalIsc += $isc;

            $outSegments[] = [
                'carrier' => $origLeg['carrier'],
                'sector' => $origLeg['origin'].'-'.$origLeg['destination'],
                'tpm' => $d['tpm'],
                'prorate_factor' => $d['prorate_factor'],
                'allocated_fare_minor_unit' => $fareShare,
            ];
        }

        return [
            'total_fare_minor_unit' => $totalTicketFareMinorUnit,
            'segments' => $outSegments,
            'interline_service_fee_minor_unit' => $totalIsc,
        ];
    }
}
