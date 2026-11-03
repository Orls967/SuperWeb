<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;

final class CircularAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'logi:circular-audit';
    }

    public function corrupt(): string
    {
        $userId = \App\Models\User::value('id') ?? \App\Models\User::create([
            'name' => 'Audit Fixture User',
            'email' => 'corrupt-fixture@test.local',
            'password' => bcrypt('password'),
        ])->id;

        $locId = \Modules\Logistics\Domain\Models\Location::value('id') ?? \Modules\Logistics\Domain\Models\Location::create([
            'code' => 'CORRUPT-LOC-01',
            'name' => 'Corrupt Hub',
            'type' => 'hub',
            'city' => 'Jakarta',
            'province' => 'DKI',
            'country' => 'ID',
            'lat_e6' => -6200000,
            'lng_e6' => 106800000,
            'is_active' => true,
        ])->id;

        // Inject a received reverse shipment with a corrupt custody hash and mismatched weight
        $shipment = Shipment::create([
            'tracking_number' => 'CORRUPT-REV-TRK-999',
            'shipper_id' => $userId,
            'consignee_name' => 'Corrupt Consignee',
            'consignee_phone' => '08123456789',
            'consignee_address' => ['address' => 'Corrupt Plant Street'],
            'origin_location_id' => $locId,
            'destination_location_id' => $locId,
            'is_reverse' => true,
            'reverse_reason' => 'material_sekunder',
            'reverse_manifest_number' => 'CORRUPT-MNF-999',
            'reverse_chain_hash' => 'invalid_short_hash', // Corrupt hash
            'material_code' => 'PET_FLAKES',
            'grade_code' => 'A',
            'net_weight_grams' => 999999, // Injected mismatched weight
            'verified_weight_grams' => 999999,
            'plant_id' => 'PLANT-CORRUPT',
            'status' => ShipmentStatus::Received->value,
            'plant_receipt_id' => 99999, // Non-existent receipt ID
            'carrier_cost_idr' => 500000,
        ]);

        return (string) $shipment->tracking_number;
    }
}
