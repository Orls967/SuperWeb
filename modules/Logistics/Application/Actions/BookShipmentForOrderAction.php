<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Contracts\ShipmentBooking;
use Modules\Logistics\Domain\Enums\LocationType;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Package;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

/**
 * Books a shipment on behalf of another module (Store, Resto, Finance).
 *
 * Payment has already been collected by the originating module;
 * this action posts the ongkir amount to lgx:unearned_freight
 * and creates the shipment + packages.
 */
class BookShipmentForOrderAction implements ShipmentBooking
{
    public function __construct(
        private readonly LogisticsLedger $lgxLedger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function bookForOrder(User $shipper, array $data): array
    {
        // Resolve origin location by code (fallback/create default hub if not present)
        $origin = Location::where('code', $data['origin_code'])->first();
        if (! $origin) {
            $origin = Location::firstOrCreate(
                ['code' => $data['origin_code']],
                [
                    'name' => 'Hub '.$data['origin_code'],
                    'type' => LocationType::HUB,
                    'city' => 'Banjarmasin',
                    'province' => 'Kalimantan Selatan',
                    'country_code' => 'ID',
                    'lat_e6' => -3300000,
                    'lng_e6' => 114500000,
                    'timezone' => 'Asia/Makassar',
                    'min_connection_minutes' => 60,
                    'is_active' => true,
                ]
            );
        }

        // Resolve or create a virtual destination from address
        $destAddress = $data['destination_address'];
        $destination = Location::firstOrCreate(
            ['code' => 'ADDR-'.strtoupper(substr(md5(json_encode($destAddress)), 0, 8))],
            [
                'name' => $destAddress['city'].' - '.($destAddress['street'] ?? 'Alamat Penerima'),
                'type' => LocationType::CUSTOMER_POINT,
                'city' => $destAddress['city'],
                'province' => $destAddress['province'] ?? '-',
                'country_code' => 'ID',
                'lat_e6' => 0,
                'lng_e6' => 0,
                'timezone' => 'Asia/Jakarta',
                'min_connection_minutes' => 0,
                'is_active' => true,
            ]
        );

        return DB::transaction(function () use ($shipper, $data, $origin, $destination) {
            $trackingNumber = TrackingNumber::generate();

            $totalWeightG = 0;
            foreach ($data['packages'] as $pkg) {
                $totalWeightG += $pkg['weight_g'] ?? 0;
            }

            $shipment = Shipment::create([
                'tracking_number' => $trackingNumber,
                'shipper_id' => $shipper->id,
                'consignee_name' => $data['consignee_name'],
                'consignee_phone' => $data['consignee_phone'],
                'consignee_address' => $data['destination_address'],
                'origin_location_id' => $origin->id,
                'destination_location_id' => $destination->id,
                'service_level' => 'regular',
                'mode' => TransportMode::ROAD,
                'payment_terms' => PaymentTerms::Prepaid,
                'status' => ShipmentStatus::Booked,
                'total_chargeable_weight_g' => $totalWeightG,
                'total_amount_idr' => $data['amount_idr'],
                'declared_value_idr' => $data['declared_value_idr'] ?? 0,
                'insured' => false,
                'cod_amount_idr' => 0,
                'booked_at' => now(),
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
            ]);

            foreach ($data['packages'] as $pkg) {
                Package::create([
                    'shipment_id' => $shipment->id,
                    'weight_g' => $pkg['weight_g'] ?? 0,
                    'length_mm' => $pkg['length_mm'] ?? 0,
                    'width_mm' => $pkg['width_mm'] ?? 0,
                    'height_mm' => $pkg['height_mm'] ?? 0,
                    'description' => $pkg['description'] ?? 'Barang Kiriman',
                ]);
            }

            // Post shipping cost to unearned freight via LogisticsLedger
            $this->lgxLedger->post(
                type: TransactionType::PAYMENT,
                description: "Ongkir shipment {$trackingNumber} dari {$data['source_type']}#{$data['source_id']}",
                idempotencyKey: "lgx:order-ship:{$data['source_type']}:{$data['source_id']}",
                entries: [
                    [LogisticsLedger::BANK_CLEARING, -$data['amount_idr']],
                    [LogisticsLedger::UNEARNED_FREIGHT, $data['amount_idr']],
                ],
                referenceType: 'lgx_shipment',
                referenceId: $shipment->id,
            );

            return [
                'tracking_number' => $trackingNumber,
                'shipment_id' => $shipment->id,
            ];
        });
    }

    /**
     * {@inheritDoc}
     */
    public function cancelForOrder(string $sourceType, int $sourceId, ?string $reason = null): bool
    {
        $shipment = Shipment::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if (! $shipment) {
            return false;
        }

        if ($shipment->status === ShipmentStatus::Cancelled) {
            return true;
        }

        return DB::transaction(function () use ($shipment, $sourceType, $sourceId, $reason) {
            // Reverse unearned freight posting if shipment was prepaid and total amount > 0
            if ($shipment->total_amount_idr > 0) {
                $this->lgxLedger->post(
                    type: TransactionType::REFUND,
                    description: "Pembalik ongkir shipment {$shipment->tracking_number} dari {$sourceType}#{$sourceId}".($reason ? ": {$reason}" : ''),
                    idempotencyKey: "lgx:order-cancel:{$sourceType}:{$sourceId}",
                    entries: [
                        [LogisticsLedger::BANK_CLEARING, $shipment->total_amount_idr],
                        [LogisticsLedger::UNEARNED_FREIGHT, -$shipment->total_amount_idr],
                    ],
                    referenceType: 'lgx_shipment',
                    referenceId: $shipment->id,
                );
            }

            $shipment->transitionTo(ShipmentStatus::Cancelled);
            $shipment->cancelled_at = now();
            $shipment->save();

            return true;
        });
    }
}
