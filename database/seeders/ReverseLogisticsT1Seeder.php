<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Location;
use Modules\Manufacturing\Domain\Models\Material;
use Modules\Manufacturing\Domain\Models\Plant;

class ReverseLogisticsT1Seeder extends Seeder
{
    private const TOTAL_SHIPMENTS = 2000;
    private const REVERSE_SHIPMENTS = 500;
    private const FLEET_COUNT = 50;

    public function run(): void
    {
        $now = Carbon::now();

        // 1. User pengirim & penerima
        $user = User::firstOrCreate(
            ['email' => 't1-reverse-admin@superweb.test'],
            ['name' => 'T1 Reverse Admin', 'password' => bcrypt('password'), 'role' => 'admin']
        );

        // 2. Buat 5 Sumber (Hubs / Origins)
        $sourceLocations = [];
        $sourceNames = ['Hub Sirkular Cikarang', 'Hub Sirkular Surabaya', 'Hub Sirkular Semarang', 'Hub Sirkular Medan', 'Hub Sirkular Makassar'];
        foreach ($sourceNames as $i => $name) {
            $loc = Location::firstOrCreate(
                ['code' => "SRC-HUB-0{$i}"],
                [
                    'name' => $name,
                    'type' => 'hub',
                    'city' => explode(' ', $name)[2],
                    'province' => 'Provinsi ' . ($i + 1),
                    'country' => 'ID',
                    'lat_e6' => -6200000 + ($i * 100000),
                    'lng_e6' => 106800000 + ($i * 100000),
                    'is_active' => true,
                ]
            );
            $sourceLocations[] = $loc->id;
        }

        // 3. Buat 3 Pabrik Manufaktur
        $plants = [];
        $plantCodes = ['PLANT-JKT-01', 'PLANT-SBY-02', 'PLANT-BDG-03'];
        foreach ($plantCodes as $code) {
            $plant = Plant::firstOrCreate(
                ['code' => $code],
                [
                    'id' => (string) Str::uuid(),
                    'name' => "Pabrik Daur Ulang Manufaktur {$code}",
                    'nominal_capacity_per_day' => 50000,
                    'is_active' => true,
                ]
            );
            $plants[] = $plant->code;
        }

        // 4. Buat Material Sekunder di Manufaktur
        $materialCodes = ['PET_FLAKES', 'ALUMINUM_SCRAP', 'RECYCLED_HDPE', 'CORRUGATED_PAPER'];
        $materialModels = [];
        foreach ($materialCodes as $mCode) {
            $mat = Material::firstOrCreate(
                ['code' => $mCode],
                [
                    'id' => (string) Str::uuid(),
                    'name' => "Bahan Baku Sekunder {$mCode}",
                    'kind' => 'raw',
                    'base_uom' => 'kg',
                    'is_active' => true,
                ]
            );
            $materialModels[$mCode] = $mat;
        }

        // 5. Buat 5 Carrier Ekspedisi & 50 Armada Truk (via core_vehicles & lgx_trucks)
        $carrierIds = [];
        for ($c = 1; $c <= 5; $c++) {
            $existingCarrier = DB::table('lgx_carriers')->where('code', "CARRIER-RL-0{$c}")->first();
            $carrierId = $existingCarrier ? $existingCarrier->id : DB::table('lgx_carriers')->insertGetId([
                'code' => "CARRIER-RL-0{$c}",
                'name' => "PT Ekspedisi Sirkular 0{$c}",
                'mode' => 'road',
                'payment_terms_days' => 14,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $carrierIds[] = $carrierId;
        }

        $truckIds = [];
        for ($i = 1; $i <= self::FLEET_COUNT; $i++) {
            $plate = sprintf('B %04d RL', $i);
            $existingVehicle = DB::table('core_vehicles')->where('plate_number', $plate)->first();
            $vehicleId = $existingVehicle ? $existingVehicle->id : DB::table('core_vehicles')->insertGetId([
                'user_id' => $user->id,
                'plate_number' => $plate,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $existingTruck = DB::table('lgx_trucks')->where('plate_number', $plate)->first();
            $truckId = $existingTruck ? $existingTruck->id : DB::table('lgx_trucks')->insertGetId([
                'vehicle_id' => $vehicleId,
                'plate_number' => $plate,
                'type' => $i % 2 === 0 ? 'cdd' : 'fuso',
                'payload_kg' => 8000,
                'volume_dm3' => 24000,
                'required_license' => 'SIM B1 Umum',
                'status' => 'available',
                'current_location_id' => $sourceLocations[$i % count($sourceLocations)],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $truckIds[] = $truckId;
        }

        // 6. Buat 2.000 Shipment (1.500 forward biasa, 500 reverse)
        $shipmentsData = [];
        $receiptsData = [];
        $lotsData = [];

        $statuses = [
            ShipmentStatus::Requested->value,
            ShipmentStatus::PickedUp->value,
            ShipmentStatus::AtHub->value,
            ShipmentStatus::DeliveredToPlant->value,
            ShipmentStatus::Received->value,
            ShipmentStatus::Rejected->value,
        ];

        $reverseTypes = ['recycling', 'return', 'pallet_recovery', 'warranty', 'recall'];
        $grades = ['A', 'B', 'C', 'SCRAP'];

        $receiptCounter = 1;

        for ($i = 1; $i <= self::TOTAL_SHIPMENTS; $i++) {
            $isReverse = ($i <= self::REVERSE_SHIPMENTS);
            $trkNum = sprintf($isReverse ? 'TRK-REV-%06d' : 'TRK-FWD-%06d', $i);
            $origin = $sourceLocations[$i % count($sourceLocations)];
            $dest = $sourceLocations[($i + 1) % count($sourceLocations)];
            $plant = $plants[$i % count($plants)];
            $mCode = $materialCodes[$i % count($materialCodes)];
            $grade = $grades[$i % count($grades)];
            $weight = 10000 + (($i * 137) % 40000); // 10kg - 50kg in grams

            $status = $isReverse ? $statuses[$i % count($statuses)] : 'delivered';

            $manifestNo = $isReverse ? sprintf('MNF-REV-%06d', $i) : null;
            $chainHash = $isReverse ? hash('sha256', "T1:{$trkNum}:{$manifestNo}:{$weight}") : null;

            $plantReceiptId = null;

            if ($isReverse && $status === ShipmentStatus::Received->value) {
                $plantReceiptId = $receiptCounter;
                $lotId = (string) Str::uuid();

                $receiptsData[] = [
                    'id' => $plantReceiptId,
                    'reverse_shipment_id' => $i,
                    'material_lot_id' => $lotId,
                    'material_code' => $mCode,
                    'grade_code' => $grade,
                    'quantity_grams' => $weight,
                    'unit_price_per_kg_idr' => 15000,
                    'total_value_idr' => (int) round(($weight / 1000) * 15000),
                    'idempotency_key' => "T1-RCP-IDEM-{$i}",
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                $lotsData[] = [
                    'id' => $lotId,
                    'material_id' => $materialModels[$mCode]->id,
                    'lot_number' => sprintf('LOT-REV-%06d', $plantReceiptId),
                    'grade_code' => $grade,
                    'qty' => round($weight / 1000, 6),
                    'source_type' => 'recycled',
                    'source_ref' => $trkNum,
                    'source_reverse_shipment_id' => $i,
                    'status' => 'active',
                    'produced_at' => $now->toDateString(),
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                $receiptCounter++;
            }

            $shipmentsData[] = [
                'id' => $i,
                'tracking_number' => $trkNum,
                'shipper_id' => $user->id,
                'consignee_name' => 'Consignee Plant/Facility ' . $i,
                'consignee_phone' => '081234567890',
                'consignee_address' => json_encode(['address' => 'Plant Area ' . $plant, 'city' => 'Jakarta']),
                'origin_location_id' => $origin,
                'destination_location_id' => $dest,
                'destination_facility_id' => $dest,
                'status' => $status,
                'service_level' => 'regular',
                'mode' => 'road',
                'total_chargeable_weight_g' => $weight,
                'is_reverse' => $isReverse,
                'reverse_reason' => $isReverse ? $reverseTypes[$i % count($reverseTypes)] : null,
                'source_party_id' => $isReverse ? $user->id : null,
                'reverse_manifest_number' => $manifestNo,
                'reverse_chain_hash' => $chainHash,
                'material_code' => $isReverse ? $mCode : null,
                'grade_code' => $isReverse ? $grade : null,
                'net_weight_grams' => $isReverse ? $weight : null,
                'verified_weight_grams' => ($isReverse && $status === ShipmentStatus::Received->value) ? $weight : null,
                'plant_id' => $isReverse ? $plant : null,
                'plant_receipt_id' => $plantReceiptId,
                'plant_received_at' => ($isReverse && $status === ShipmentStatus::Received->value) ? $now->toDateTimeString() : null,
                'carrier_cost_idr' => $isReverse ? 75000 : 0,
                'supplier_party_id' => $isReverse ? 'SUP-T1-PARTNER' : null,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];
        }

        // Bulk insert in chunks:
        // 1. lgx_shipments
        foreach (array_chunk($shipmentsData, 200) as $chunk) {
            DB::table('lgx_shipments')->insert($chunk);
        }

        // 2. mfg_material_lots (references lgx_shipments.id)
        foreach (array_chunk($lotsData, 200) as $chunk) {
            DB::table('mfg_material_lots')->insert($chunk);
        }

        // 3. mfg_recycled_receipts (references lgx_shipments.id & mfg_material_lots.id)
        foreach (array_chunk($receiptsData, 200) as $chunk) {
            DB::table('mfg_recycled_receipts')->insert($chunk);
        }
    }
}
