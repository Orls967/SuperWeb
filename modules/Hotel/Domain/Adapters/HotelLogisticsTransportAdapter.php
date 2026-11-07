<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

/**
 * Adaptor Integrasi: Pemesanan Armada Transportasi Shuttle Airport / Tur Wisata dari Modul Logistics (Fase 45).
 */
final class HotelLogisticsTransportAdapter
{
    /**
     * @return array{trip_id: string, pickup_location: string, destination: string, vehicle_type: string, status: string}
     */
    public function dispatchShuttleTrip(
        string $guestId,
        string $pickupTime,
        string $pickupLocation,
        string $destination,
        string $vehicleType = 'EXECUTIVE_VAN'
    ): array {
        $tripId = 'SHU-LOG-'.date('Ymd').'-'.substr(md5($guestId.$pickupTime), 0, 8);

        return [
            'trip_id' => $tripId,
            'pickup_location' => $pickupLocation,
            'destination' => $destination,
            'vehicle_type' => $vehicleType,
            'status' => 'DISPATCHED_TO_LOGISTICS_FLEET',
        ];
    }
}
