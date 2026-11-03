<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;
use Modules\Logistics\Domain\Models\Autonomous\DroneMission;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Modules\Logistics\Domain\Models\Shipment;

class ColdChainDroneT1Seeder extends Seeder
{
    public function run(): void
    {
        $service = app(AutonomousLogisticsService::class);
        $now = Carbon::parse('2026-11-03 12:00:00');

        $this->command?->info('Menjalankan ColdChainDroneT1Seeder (5.000 pembacaan suhu, 200 leg reefer, 100 misi drone)...');

        // 1. Buat 200 shipment / leg reefer & 5.000 pembacaan suhu (25 bacaan per shipment)
        $carrierIds = [10, 20, 30, 40, 50];
        $readingsData = [];
        $shipmentIds = [];

        // Buat dummy shipments jika belum ada
        for ($i = 1; $i <= 200; $i++) {
            $carrierId = $carrierIds[($i - 1) % count($carrierIds)];
            $code = sprintf('SHP-COLD-%04d', $i);

            $existing = DB::table('lgx_shipments')->where('tracking_number', $code)->first();
            if ($existing) {
                $shipmentId = $existing->id;
            } else {
                $shipmentId = DB::table('lgx_shipments')->insertGetId([
                    'tracking_number' => $code,
                    'shipper_id' => 1,
                    'origin_location_id' => 1,
                    'destination_location_id' => 2,
                    'consignee_name' => 'RSUD Cold Storage Central',
                    'consignee_phone' => '081234567890',
                    'consignee_address' => json_encode(['address' => 'Jl. Kesehatan No. 10', 'city' => 'Jakarta']),
                    'status' => 'in_transit',
                    'service_level' => 'regular',
                    'mode' => 'road',
                    'payment_terms' => 'prepaid',
                    'total_chargeable_weight_g' => 50_000,
                    'total_amount_idr' => 3_500_000,
                    'carrier_cost_idr' => 2_500_000,
                    'created_at' => $now->copy()->subHours(10),
                    'updated_at' => $now,
                ]);
            }
            $shipmentIds[] = $shipmentId;

            // 25 pembacaan per shipment = 5.000 total pembacaan
            // 15 shipment pertama disimulasikan mengalami breach (> 10 menit)
            $isBreach = ($i <= 15);

            for ($r = 0; $r < 25; $r++) {
                $readingTime = $now->copy()->subMinutes(250 - ($r * 10)); // tiap 10 menit

                if ($isBreach && $r >= 10 && $r <= 12) {
                    // Excursion 20 menit (r=10 s.d. 12 = t=0, t=10m, t=20m)
                    $tempC10 = 125; // 12.5 °C
                    $excursion = true;
                } else {
                    $tempC10 = 40; // 4.0 °C (normal)
                    $excursion = false;
                }

                $readingsData[] = [
                    'shipment_id' => $shipmentId,
                    'container_id' => null,
                    'temp_c10' => $tempC10,
                    'min_c10' => 20, // 2.0 °C
                    'max_c10' => 80, // 8.0 °C
                    'excursion' => $excursion,
                    'recorded_by' => 'iot_sensor_reefer_01',
                    'recorded_at' => $readingTime,
                    'created_at' => $readingTime,
                    'updated_at' => $readingTime,
                ];
            }
        }

        // Insert bulk pembacaan suhu
        foreach (array_chunk($readingsData, 500) as $chunk) {
            DB::table('lgx_temperature_readings')->insert($chunk);
        }

        // Proses breach untuk 15 shipment yang bermasalah via service resmi
        for ($i = 1; $i <= 15; $i++) {
            $carrierId = $carrierIds[($i - 1) % count($carrierIds)];
            $code = sprintf('SHP-COLD-%04d', $i);

            $service->recordTemperatureCheck(
                shipmentCode: $code,
                carrierId: $carrierId,
                recordedTempC: 12.5,
                maxAllowedTempC: 8.0,
                durationMinutes: 20, // 20 menit (> 10 menit)
                carrierPayableIdr: 2_500_000,
                legId: $shipmentIds[$i - 1],
                episodeId: "EP-T1-{$i}"
            );
        }

        // 2. Buat 10 drone units
        $drones = [];
        for ($d = 1; $d <= 10; $d++) {
            $drone = DroneUnit::firstOrCreate(
                ['drone_code' => sprintf('DRN-T1-%02d', $d)],
                [
                    'model_name' => "Autonomous Flyer Model {$d}",
                    'battery_percent' => 100,
                    'max_payload_kg' => 5.0,
                    'max_range_km' => 15.0,
                    'status' => 'IDLE',
                ]
            );
            $drones[] = $drone;
        }

        // 3. Buat 100 misi drone (D80-4 POD)
        for ($m = 1; $m <= 100; $m++) {
            $drone = $drones[($m - 1) % count($drones)];
            $missionCode = sprintf('DRN-MSN-%04d', $m);
            $shipmentCode = sprintf('SHP-DRN-%04d', $m);
            $geohash = 'qqguygv1t'; // Monas Jakarta POD

            $mission = DroneMission::firstOrCreate(
                ['mission_code' => $missionCode],
                [
                    'drone_unit_id' => $drone->id,
                    'shipment_code' => $shipmentCode,
                    'package_weight_kg' => 2.0 + (($m % 30) / 10),
                    'distance_km' => 5.0 + (($m % 80) / 10),
                    'destination_geo_hash' => $geohash,
                    'status' => 'DELIVERED',
                    'pod_signature_hash' => hash('sha256', "{$missionCode}:{$geohash}:DELIVERED"),
                    'delivered_at' => $now->copy()->subMinutes(100 - $m),
                    'created_at' => $now->copy()->subMinutes(120 - $m),
                    'updated_at' => $now,
                ]
            );
        }

        $this->command?->info('ColdChainDroneT1Seeder selesai: 5.000 pembacaan suhu, 200 shipment reefer, 15 hold breach, 10 drone, 100 misi drone.');
    }
}
