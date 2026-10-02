<?php

declare(strict_types=1);

namespace Modules\Logistics\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\TrackingEvent;
use Modules\Logistics\Domain\Models\Truck;
use Modules\Logistics\Domain\Models\Vessel;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;

/**
 * Seeder skala enterprise untuk modul Logistik:
 * - >= 300 truk
 * - >= 20 kapal
 * - >= 5.000 kontainer ISO 6346
 * - >= 200.000 shipment (12 bulan riwayat)
 * - >= 2.000.000 tracking event (hash chain per shipment)
 * - B2B postpaid invoice ledger history
 *
 * Menggunakan bulk batch inserts teroptimasi mengikuti pola DemoLargeSeeder.
 */
class LogisticsLargeSeeder extends Seeder
{
    private const TARGET_TRUCKS = 300;

    private const TARGET_VESSELS = 20;

    private const TARGET_CONTAINERS = 5_000;

    private const TARGET_SHIPMENTS = 200_000;

    private const MIN_TRACKING_EVENTS_PER_SHIPMENT = 10;

    private const CHUNK_SIZE = 500;

    private array $locationIds = [];

    private array $hubIds = [];

    public function run(): void
    {
        $startTime = microtime(true);
        $this->command?->info('🚀 Memulai LogisticsLargeSeeder (Skala Enterprise Logistik)...');

        $this->loadLocations();

        $this->seedTrucks();
        $this->seedVessels();
        $this->seedContainers();
        $shipmentIds = $this->seedShipments();
        $this->seedTrackingEvents($shipmentIds);
        $this->seedPostpaidHistory();

        $duration = round(microtime(true) - $startTime, 2);
        $this->command?->info("✨ LogisticsLargeSeeder selesai dalam {$duration} detik.");
    }

    private function loadLocations(): void
    {
        $locations = Location::all();
        $this->locationIds = $locations->pluck('id')->all();
        $this->hubIds = $locations->filter(fn ($l) => str_starts_with($l->code, 'HUB-'))
            ->pluck('id')->all();

        if (empty($this->hubIds)) {
            $this->hubIds = $this->locationIds;
        }
    }

    /**
     * 1. Tambah truk hingga >= 300 unit total
     */
    private function seedTrucks(): void
    {
        $existing = Truck::count();
        $need = max(0, self::TARGET_TRUCKS - $existing);
        if ($need === 0) {
            $this->command?->info('  [1/6] Truk sudah >= '.self::TARGET_TRUCKS." ({$existing}). Skip.");

            return;
        }

        $this->command?->info("  [1/6] Menambah {$need} truk baru (total target ".self::TARGET_TRUCKS.')...');

        DB::disableQueryLog();
        $batch = [];
        $now = now()->toDateTimeString();
        $adminId = DB::table('users')->where('role', 'admin')->value('id') ?? 1;
        $carId = DB::table('dex_cars')->value('id');

        for ($i = 1; $i <= $need; $i++) {
            $num = $existing + $i;
            $plate = sprintf('DA %04d LX', 1000 + $num);

            $vehicleId = DB::table('core_vehicles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'user_id' => $adminId,
                'car_id' => $carId,
                'plate_number' => $plate,
                'vin' => sprintf('MHISZLGX2026%06d', $num + 100000),
                'color' => 'White/Blue Sari Ranah',
                'odometer_km' => $num * 3000,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $batch[] = [
                'vehicle_id' => $vehicleId,
                'plate_number' => $plate,
                'type' => ['cde', 'cdd', 'fuso', 'tronton', 'tractor_head'][$num % 5],
                'payload_kg' => [4000, 7500, 16000, 25000, 28000][$num % 5],
                'volume_dm3' => [14000, 20000, 35000, 50000, 0][$num % 5],
                'required_license' => $num % 5 < 2 ? 'SIM B1 Umum' : 'SIM B2 Umum',
                'service_interval_m' => 10_000_000,
                'odometer_m' => $num * 3_000_000,
                'status' => FleetStatus::AVAILABLE->value,
                'current_location_id' => $this->hubIds[$num % count($this->hubIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= self::CHUNK_SIZE) {
                DB::table('lgx_trucks')->insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('lgx_trucks')->insert($batch);
        }
    }

    /**
     * 2. Tambah kapal hingga >= 20 unit total
     */
    private function seedVessels(): void
    {
        $existing = Vessel::count();
        $need = max(0, self::TARGET_VESSELS - $existing);
        if ($need === 0) {
            $this->command?->info('  [2/6] Kapal sudah >= '.self::TARGET_VESSELS." ({$existing}). Skip.");

            return;
        }

        $this->command?->info("  [2/6] Menambah {$need} kapal baru...");

        $portIds = Location::where('code', 'like', 'PORT-%')->pluck('id')->all();
        if (empty($portIds)) {
            $portIds = $this->locationIds;
        }

        $batch = [];
        $now = now()->toDateTimeString();
        // Use base IMO numbers that pass check-digit validation (pre-calculated)
        $baseImo = 9400000;

        for ($i = 1; $i <= $need; $i++) {
            $num = $existing + $i;
            // Generate valid IMO number with check digit
            $sixDigit = $baseImo + $num;
            $digits = str_split((string) $sixDigit);
            $check = 0;
            for ($d = 0; $d < 6; $d++) {
                $check += (int) $digits[$d] * (7 - $d);
            }
            $imo = $sixDigit.($check % 10);

            $batch[] = [
                'imo_number' => (string) $imo,
                'name' => sprintf('KM Sari Ranah Kargo %03d', $num),
                'flag' => 'ID',
                'teu_capacity' => rand(400, 1200),
                'reefer_plugs' => rand(40, 120),
                'dwt_tonnes' => rand(8000, 20000),
                'status' => FleetStatus::AVAILABLE->value,
                'current_location_id' => $portIds[$num % count($portIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= self::CHUNK_SIZE) {
                DB::table('lgx_vessels')->insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('lgx_vessels')->insert($batch);
        }
    }

    /**
     * 3. Tambah kontainer hingga >= 5.000 unit total
     */
    private function seedContainers(): void
    {
        $existing = Container::count();
        $need = max(0, self::TARGET_CONTAINERS - $existing);
        if ($need === 0) {
            $this->command?->info('  [3/6] Kontainer sudah >= '.self::TARGET_CONTAINERS." ({$existing}). Skip.");

            return;
        }

        $this->command?->info("  [3/6] Menambah {$need} kontainer ISO 6346...");

        DB::disableQueryLog();
        $sizeTypes = ['22G1', '42G1', '45G1', '22R1', '45R1'];
        $tareSpecs = [
            '22G1' => [2_200, 30_480],
            '42G1' => [3_750, 32_500],
            '45G1' => [3_900, 32_500],
            '22R1' => [3_050, 30_480],
            '45R1' => [4_800, 34_000],
        ];

        $depotIds = Location::where('code', 'like', 'DEP-%')
            ->orWhere('code', 'like', 'PORT-%')
            ->orWhere('code', 'like', 'CFS-%')
            ->pluck('id')->all();
        if (empty($depotIds)) {
            $depotIds = $this->locationIds;
        }

        $batch = [];
        $now = now()->toDateTimeString();
        $ownerCodes = ['SRX', 'BRN', 'KLS', 'MHK', 'PLU'];

        for ($i = 1; $i <= $need; $i++) {
            $num = $existing + $i;
            $serial = 200000 + $num;
            $ownerCode = $ownerCodes[$num % count($ownerCodes)];
            $cntNumber = Iso6346Validator::generate($ownerCode, 'U', $serial);
            $st = $sizeTypes[$num % 5];
            [$tare, $gross] = $tareSpecs[$st];

            $batch[] = [
                'container_number' => $cntNumber,
                'size_type' => $st,
                'tare_kg' => $tare,
                'max_gross_kg' => $gross,
                'status' => FleetStatus::AVAILABLE->value,
                'current_location_id' => $depotIds[$num % count($depotIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= self::CHUNK_SIZE) {
                DB::table('lgx_containers')->insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('lgx_containers')->insert($batch);
        }
    }

    /**
     * 4. 200.000+ shipments spanning 12 months
     *
     * @return array<int> shipment IDs
     */
    private function seedShipments(): array
    {
        $targetCount = (int) env('LOGISTICS_LARGE_SHIPMENTS', self::TARGET_SHIPMENTS);
        $this->command?->info("  [4/6] Memasukkan {$targetCount} shipment (12 bulan riwayat)...");

        DB::disableQueryLog();
        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(12)->timestamp;
        $endDate = $now->timestamp;
        $rangeSeconds = max(1, $endDate - $startDate);

        $shipperIds = DB::table('users')->where('role', 'shipper')->pluck('id')->all();
        if (empty($shipperIds)) {
            $shipperIds = [1]; // fallback
        }
        $driverIds = DB::table('lgx_drivers')->pluck('id')->all();

        $serviceLevels = [ServiceLevel::Regular->value, ServiceLevel::Express->value, ServiceLevel::SameDay->value];
        $modes = [TransportMode::ROAD->value, TransportMode::SEA->value, TransportMode::AIR->value];
        $statuses = [
            ShipmentStatus::Delivered->value,
            ShipmentStatus::Delivered->value,
            ShipmentStatus::Delivered->value,
            ShipmentStatus::Delivered->value,
            ShipmentStatus::Delivered->value,
            ShipmentStatus::Delivered->value,
            ShipmentStatus::Delivered->value,
            ShipmentStatus::InTransit->value,
            ShipmentStatus::Booked->value,
            ShipmentStatus::Cancelled->value,
        ];

        $chunksCount = (int) ceil($targetCount / self::CHUNK_SIZE);
        $allIds = [];

        // Record max ID before insertion so we only collect newly created IDs
        $maxIdBefore = (int) DB::table('lgx_shipments')->max('id');

        DB::transaction(function () use (
            $chunksCount,
            $targetCount,
            $startDate,
            $rangeSeconds,
            $shipperIds,
            $driverIds,
            $serviceLevels,
            $modes,
            $statuses,
            &$allIds
        ) {
            for ($c = 0; $c < $chunksCount; $c++) {
                $batch = [];
                $currentChunkRows = min(self::CHUNK_SIZE, $targetCount - ($c * self::CHUNK_SIZE));

                for ($r = 0; $r < $currentChunkRows; $r++) {
                    $idx = ($c * self::CHUNK_SIZE) + $r + 1;
                    $randomTs = $startDate + (int) (($idx / $targetCount) * $rangeSeconds);
                    $bookedAt = date('Y-m-d H:i:s', $randomTs);

                    $status = $statuses[$idx % count($statuses)];
                    $totalAmount = rand(50_000, 5_000_000);
                    $chargeableWeight = rand(500, 50_000);

                    $deliveredAt = null;
                    $cancelledAt = null;
                    $revenueAt = null;
                    if ($status === 'delivered') {
                        $deliveredAt = date('Y-m-d H:i:s', $randomTs + rand(86400, 604800));
                        $revenueAt = $deliveredAt;
                    } elseif ($status === 'cancelled') {
                        $cancelledAt = date('Y-m-d H:i:s', $randomTs + rand(3600, 86400));
                    }

                    $trackingNumber = TrackingNumber::generate();
                    $shipperId = $shipperIds[$idx % count($shipperIds)];
                    $driverId = ! empty($driverIds) ? $driverIds[$idx % count($driverIds)] : null;

                    $originId = $this->locationIds[$idx % count($this->locationIds)];
                    $destId = $this->locationIds[($idx + 3) % count($this->locationIds)];

                    $batch[] = [
                        'tracking_number' => $trackingNumber,
                        'shipper_id' => $shipperId,
                        'consignee_name' => 'Penerima Batch '.$idx,
                        'consignee_phone' => sprintf('08120000%04d', $idx % 10000),
                        'consignee_address' => json_encode(['street' => "Jl. Batch {$idx}", 'city' => 'Banjarmasin']),
                        'origin_location_id' => $originId,
                        'destination_location_id' => $destId,
                        'service_level' => $serviceLevels[$idx % count($serviceLevels)],
                        'mode' => $modes[$idx % count($modes)],
                        'incoterm' => 'DAP',
                        'declared_value_idr' => rand(0, 10_000_000),
                        'insured' => $idx % 10 === 0 ? 1 : 0,
                        'cod_amount_idr' => $idx % 15 === 0 ? rand(50_000, 500_000) : 0,
                        'payment_terms' => $idx % 5 === 0 ? 'postpaid' : 'prepaid',
                        'status' => $status,
                        'total_chargeable_weight_g' => $chargeableWeight,
                        'total_amount_idr' => $totalAmount,
                        'cancellation_fee_idr' => 0,
                        'delivery_otp_hash' => null,
                        'failed_delivery_attempts' => 0,
                        'quote_id' => null,
                        'driver_id' => $driverId,
                        'invoice_id' => null,
                        'source_type' => null,
                        'source_id' => null,
                        'booked_at' => $bookedAt,
                        'picked_up_at' => $status !== 'cancelled' ? date('Y-m-d H:i:s', $randomTs + 3600) : null,
                        'delivered_at' => $deliveredAt,
                        'revenue_recognized_at' => $revenueAt,
                        'cancelled_at' => $cancelledAt,
                        'created_at' => $bookedAt,
                        'updated_at' => $deliveredAt ?? $cancelledAt ?? $bookedAt,
                    ];
                }

                // Use insert for performance — collect IDs after
                DB::table('lgx_shipments')->insert($batch);

                if (($c + 1) % 50 === 0 || ($c + 1) === $chunksCount) {
                    $insertedSoFar = min(($c + 1) * self::CHUNK_SIZE, $targetCount);
                    $this->command?->line("    -> Tersimpan {$insertedSoFar} / {$targetCount} shipments...");
                }
            }
        });

        // Collect only NEWLY created shipment IDs (skip pre-existing ones that already have tracking events)
        $allIds = DB::table('lgx_shipments')
            ->where('id', '>', $maxIdBefore)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $allIds;
    }

    /**
     * 5. >= 2.000.000 tracking events (10+ per shipment, hash chain)
     *
     * @param  array<int>  $shipmentIds
     */
    private function seedTrackingEvents(array $shipmentIds): void
    {
        $totalShipments = count($shipmentIds);
        $targetEvents = max(2_000_000, $totalShipments * self::MIN_TRACKING_EVENTS_PER_SHIPMENT);
        $this->command?->info("  [5/6] Memasukkan ~{$targetEvents} tracking events (hash chain)...");

        DB::disableQueryLog();

        $eventTypes = [
            'BOOKED', 'PICKED_UP', 'ARRIVED_HUB', 'DEPARTED_HUB',
            'IN_TRANSIT', 'ARRIVED_HUB', 'SORTED', 'DEPARTED_HUB',
            'OUT_FOR_DELIVERY', 'DELIVERED',
        ];

        $totalInserted = 0;
        $batch = [];

        foreach ($shipmentIds as $sIdx => $shipmentId) {
            $eventsPerShipment = self::MIN_TRACKING_EVENTS_PER_SHIPMENT;

            $prevHash = TrackingEvent::genesisHash($shipmentId);
            $baseTs = Carbon::now()->subMonths(12)->addSeconds((int) (($sIdx / max(1, $totalShipments)) * 365 * 86400));

            for ($seq = 1; $seq <= $eventsPerShipment; $seq++) {
                $eventType = $eventTypes[($seq - 1) % count($eventTypes)];
                $occurredAt = $baseTs->copy()->addHours($seq * rand(2, 12));
                $payload = ['sequence' => $seq, 'batch' => true];

                $hash = TrackingEvent::calculateHash(
                    $prevHash,
                    $seq,
                    $eventType,
                    $payload,
                    $occurredAt,
                );

                $locId = $this->locationIds[($sIdx + $seq) % count($this->locationIds)];

                $batch[] = [
                    'shipment_id' => $shipmentId,
                    'sequence' => $seq,
                    'event_type' => $eventType,
                    'location_id' => $locId,
                    'actor_id' => null,
                    'actor_role' => 'system',
                    'description' => "{$eventType} event #{$seq}",
                    'payload' => json_encode($payload),
                    'occurred_at' => $occurredAt->toDateTimeString(),
                    'prev_hash' => $prevHash,
                    'hash' => $hash,
                    'created_at' => $occurredAt->toDateTimeString(),
                ];

                $prevHash = $hash;
                $totalInserted++;

                if (count($batch) >= self::CHUNK_SIZE) {
                    DB::table('lgx_tracking_events')->insert($batch);
                    $batch = [];

                    if ($totalInserted % 100_000 === 0) {
                        $this->command?->line("    -> Tersimpan {$totalInserted} tracking events...");
                    }
                }
            }
        }

        if (! empty($batch)) {
            DB::table('lgx_tracking_events')->insert($batch);
        }

        $this->command?->line("    -> Total tracking events: {$totalInserted}");
    }

    /**
     * 6. Riwayat invoice B2B postpaid 12 bulan
     */
    private function seedPostpaidHistory(): void
    {
        $this->command?->info('  [6/6] Menghasilkan 12 bulan riwayat invoice B2B postpaid...');

        $accounts = DB::table('lgx_shipper_accounts')->where('is_active', true)->get();
        if ($accounts->isEmpty()) {
            $this->command?->line('    -> Tidak ada shipper account aktif. Skip.');

            return;
        }

        $now = Carbon::now();
        $batch = [];

        foreach ($accounts as $account) {
            for ($m = 11; $m >= 0; $m--) {
                $period = $now->copy()->subMonths($m);
                $periodStr = $period->format('Y-m');
                $invoiceNumber = sprintf('INV-LGX-%s-%04d', str_replace('-', '', $periodStr), $account->id);

                $totalAmount = rand(5_000_000, 50_000_000);

                $status = $m > 1 ? 'paid' : ($m === 1 ? 'overdue' : 'issued');
                $paidAt = $status === 'paid' ? $period->copy()->addDays(rand(5, 25))->toDateTimeString() : null;

                $batch[] = [
                    'invoice_number' => $invoiceNumber,
                    'kind' => 'freight',
                    'shipper_id' => $account->shipper_id,
                    'billing_period' => $periodStr,
                    'total_amount_idr' => $totalAmount,
                    'paid_amount_idr' => $status === 'paid' ? $totalAmount : 0,
                    'status' => $status === 'overdue' ? 'unpaid' : $status,
                    'due_date' => $period->copy()->addDays(30)->toDateString(),
                    'paid_at' => $paidAt,
                    'created_at' => $period->copy()->startOfMonth()->toDateTimeString(),
                    'updated_at' => $paidAt ?? $period->copy()->startOfMonth()->toDateTimeString(),
                ];

                if (count($batch) >= self::CHUNK_SIZE) {
                    DB::table('lgx_invoices')->insert($batch);
                    $batch = [];
                }
            }
        }

        if (! empty($batch)) {
            DB::table('lgx_invoices')->insert($batch);
        }
    }
}
