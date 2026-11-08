<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

use DomainException;
use Illuminate\Support\Facades\Log;

class AviationBaggageReconciliationService
{
    /**
     * Catat peristiwa pemindaian rantai lacak bagasi (IATA Res 753 4 Mandatory Tracking Points).
     *
     * @param  string  $bagTagNumber  10-digit IATA bag tag (e.g. 0126998811)
     * @param  string  $trackingPoint  Titik lacak: CHECK_IN, LOAD_AIRCRAFT, TRANSFER_INTERLINE, ARRIVAL_CAROUSEL
     * @param  string  $airportIata  Kode bandara lokasi pemindaian
     * @param  string  $containerId  ULD Container ID atau Baggage Cart
     * @return array{bag_tag: string, tracking_point: string, location: string, timestamp: string, verified: bool}
     */
    public function recordBaggageScan(
        string $bagTagNumber,
        string $trackingPoint,
        string $airportIata,
        string $containerId
    ): array {
        $allowedPoints = ['CHECK_IN', 'LOAD_AIRCRAFT', 'TRANSFER_INTERLINE', 'ARRIVAL_CAROUSEL'];
        $point = strtoupper($trackingPoint);

        if (! in_array($point, $allowedPoints, true)) {
            throw new DomainException(sprintf('Titik pemindaian bagasi [%s] tidak sesuai standar IATA Res 753.', $trackingPoint));
        }

        $now = now()->toIso8601String();

        Log::info('IATA Res 753 Baggage Scan Logged', [
            'bag_tag' => $bagTagNumber,
            'tracking_point' => $point,
            'airport' => strtoupper($airportIata),
            'container_id' => $containerId,
            'scanned_at' => $now,
        ]);

        return [
            'bag_tag' => $bagTagNumber,
            'tracking_point' => $point,
            'location' => strtoupper($airportIata),
            'timestamp' => $now,
            'verified' => true,
        ];
    }
}
