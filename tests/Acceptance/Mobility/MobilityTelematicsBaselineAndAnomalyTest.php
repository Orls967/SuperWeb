<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Mobility\Application\Actions\IngestTelematicsTickAction;
use Modules\Mobility\Application\Actions\RecomputeRobustBaselineAction;
use Modules\Mobility\Domain\Events\TelematicsAnomalyDetectedEvent;
use Modules\Mobility\Domain\Models\TelematicsBaseline;
use Modules\Mobility\Domain\Models\TelematicsDevice;
use Modules\Mobility\Domain\Models\TelematicsTick;
use Tests\TestCase;

class MobilityTelematicsBaselineAndAnomalyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createDevice(string $deviceId = 'DEV-ANOM-01'): array
    {
        $user = User::factory()->create();
        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 7777 ANM',
            'vin' => 'VIN-ANOM-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device = TelematicsDevice::create([
            'device_id' => $deviceId,
            'vehicle_id' => $vehicleId,
            'serial_number' => 'SN-'.$deviceId,
            'token_hash' => hash('sha256', 'token-123'),
            'protocol' => 'OBD2_CAN',
            'status' => 'ACTIVE',
            'last_heartbeat_at' => now(),
        ]);

        return [$device, $vehicleId];
    }

    public function test_perhitungan_baseline_median_dan_mad_7_hari_lokal(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $action = app(RecomputeRobustBaselineAction::class);

        // Masukkan data tick suhu (deci-C) selama 7 hari:
        // [800, 810, 820, 830, 840, 850, 860]
        // Median = 830.
        // Deviations = [30, 20, 10, 0, 10, 20, 30] -> sorted: [0, 10, 10, 20, 20, 30, 30]
        // MAD = 20.
        $values = [800, 810, 820, 830, 840, 850, 860];
        $seq = 1;
        foreach ($values as $val) {
            TelematicsTick::create([
                'device_id' => $device->device_id,
                'vehicle_id' => $vehicleId,
                'sequence' => $seq++,
                'recorded_at' => now()->subDays(2),
                'coolant_temp_deci_c' => $val,
                'battery_mv' => 12500,
                'fuel_permil' => 700,
                'odometer_meters' => 100000 + ($seq * 1000),
                'idempotency_key' => 'key-'.$seq,
            ]);
        }

        $baseline = $action->execute(vehicleId: $vehicleId, metric: 'coolant_temp_deci_c');

        $this->assertInstanceOf(TelematicsBaseline::class, $baseline);
        $this->assertSame(830, $baseline->median_value);
        $this->assertSame(20, $baseline->mad_value);
        $this->assertSame(7, $baseline->sample_count);
    }

    public function test_mad_nol_memakai_ambang_batas_absolut_minimum_tanpa_div_by_zero(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $action = app(RecomputeRobustBaselineAction::class);

        // Seluruh tick bernilai konstan 12600 mV
        // Median = 12600, MAD = 0
        for ($i = 1; $i <= 5; $i++) {
            TelematicsTick::create([
                'device_id' => $device->device_id,
                'vehicle_id' => $vehicleId,
                'sequence' => $i,
                'recorded_at' => now()->subDays(1),
                'coolant_temp_deci_c' => 850,
                'battery_mv' => 12600,
                'fuel_permil' => 700,
                'odometer_meters' => 100000 + ($i * 1000),
                'idempotency_key' => 'key-volt-'.$i,
            ]);
        }

        $baseline = $action->execute(vehicleId: $vehicleId, metric: 'battery_mv');

        $this->assertSame(12600, $baseline->median_value);
        $this->assertSame(0, $baseline->mad_value);

        // Evaluasi anomali dengan MAD = 0 harus memakai threshold absolut config tanpa exception
        $isAnomaly = $action->isAnomaly(
            metric: 'battery_mv',
            value: 10500, // drop tegangan drastis
            baseline: $baseline
        );

        $this->assertTrue($isAnomaly);
    }

    public function test_jendela_waktu_7_hari_hanya_menghitung_tick_dalam_rentang(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $action = app(RecomputeRobustBaselineAction::class);

        // Tick 10 hari lalu (di luar jendela 7 hari)
        TelematicsTick::create([
            'device_id' => $device->device_id,
            'vehicle_id' => $vehicleId,
            'sequence' => 1,
            'recorded_at' => now()->subDays(10),
            'coolant_temp_deci_c' => 9999,
            'battery_mv' => 12500,
            'idempotency_key' => 'old-tick',
        ]);

        // Tick 3 hari lalu
        TelematicsTick::create([
            'device_id' => $device->device_id,
            'vehicle_id' => $vehicleId,
            'sequence' => 2,
            'recorded_at' => now()->subDays(3),
            'coolant_temp_deci_c' => 850,
            'battery_mv' => 12500,
            'idempotency_key' => 'recent-tick',
        ]);

        $baseline = $action->execute(vehicleId: $vehicleId, metric: 'coolant_temp_deci_c');

        $this->assertSame(1, $baseline->sample_count);
        $this->assertSame(850, $baseline->median_value);
    }

    public function test_deteksi_anomali_formula_integer_murni(): void
    {
        [$device, $vehicleId] = $this->createDevice();
        $action = app(RecomputeRobustBaselineAction::class);

        $baseline = TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'coolant_temp_deci_c',
            'median_value' => 850,
            'mad_value' => 20,
            'sample_count' => 10,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        // |850 - 870| = 20 -> 20 * 100_000_000 = 2_000_000_000
        // k_bps(35000) * 14826 * 20 = 10_378_200_000
        // 2 milyar <= 10 milyar -> BUKAN ANOMALI
        $this->assertFalse($action->isAnomaly('coolant_temp_deci_c', 870, $baseline));

        // |850 - 1200| = 350 -> 350 * 100_000_000 = 35_000_000_000
        // 35 milyar > 10 milyar -> ANOMALI TERDETEKSI
        $this->assertTrue($action->isAnomaly('coolant_temp_deci_c', 1200, $baseline));
    }

    public function test_anomali_memancarkan_event_auto_telematics_anomaly_detected(): void
    {
        Event::fake([TelematicsAnomalyDetectedEvent::class]);
        [$device, $vehicleId] = $this->createDevice();

        TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'coolant_temp_deci_c',
            'median_value' => 850,
            'mad_value' => 20,
            'sample_count' => 10,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        $ingestAction = app(IngestTelematicsTickAction::class);
        $ingestAction->execute(
            device: $device,
            token: 'token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 3000,
            speedKmh: 60,
            coolantTempDeciC: 1250, // Anomali overheating 125 C
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000
        );

        Event::assertDispatched(TelematicsAnomalyDetectedEvent::class, function ($e) use ($vehicleId) {
            return $e->vehicleId === $vehicleId && $e->metric === 'coolant_temp_deci_c';
        });
    }

    public function test_satu_anomali_per_hari_lokal_hanya_memicu_satu_draf_booking(): void
    {
        [$device, $vehicleId] = $this->createDevice();

        TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'coolant_temp_deci_c',
            'median_value' => 850,
            'mad_value' => 20,
            'sample_count' => 10,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        $ingestAction = app(IngestTelematicsTickAction::class);

        // Tick pertama dengan anomali pagi hari
        $ingestAction->execute(
            device: $device,
            token: 'token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 3000,
            speedKmh: 60,
            coolantTempDeciC: 1250,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000
        );

        // Tick kedua dengan anomali yang sama sore hari di hari yang sama
        $ingestAction->execute(
            device: $device,
            token: 'token-123',
            sequence: 2,
            recordedAt: now()->addHours(6),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 3000,
            speedKmh: 60,
            coolantTempDeciC: 1260,
            batteryMv: 12500,
            fuelPermil: 680,
            odometerMeters: 100100
        );

        // Hanya 1 record anomali harian yang tersimpan
        $count = DB::table('oto_telematics_anomalies')
            ->where('vehicle_id', $vehicleId)
            ->where('metric', 'coolant_temp_deci_c')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_anomali_pada_hari_berikutnya_membuat_record_baru(): void
    {
        [$device, $vehicleId] = $this->createDevice();

        TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'coolant_temp_deci_c',
            'median_value' => 850,
            'mad_value' => 20,
            'sample_count' => 10,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        $ingestAction = app(IngestTelematicsTickAction::class);

        // Hari ini
        $ingestAction->execute(
            device: $device,
            token: 'token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 3000,
            speedKmh: 60,
            coolantTempDeciC: 1250,
            batteryMv: 12500,
            fuelPermil: 700,
            odometerMeters: 100000
        );

        // Besok
        $ingestAction->execute(
            device: $device,
            token: 'token-123',
            sequence: 2,
            recordedAt: now()->addDay(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 3000,
            speedKmh: 60,
            coolantTempDeciC: 1250,
            batteryMv: 12500,
            fuelPermil: 680,
            odometerMeters: 100200
        );

        $count = DB::table('oto_telematics_anomalies')
            ->where('vehicle_id', $vehicleId)
            ->where('metric', 'coolant_temp_deci_c')
            ->count();

        $this->assertSame(2, $count);
    }

    public function test_metrik_berbeda_di_hari_sama_memicu_anomali_masing_masing(): void
    {
        [$device, $vehicleId] = $this->createDevice();

        TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'coolant_temp_deci_c',
            'median_value' => 850,
            'mad_value' => 20,
            'sample_count' => 10,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        TelematicsBaseline::create([
            'vehicle_id' => $vehicleId,
            'metric' => 'battery_mv',
            'median_value' => 12600,
            'mad_value' => 100,
            'sample_count' => 10,
            'calculated_at' => now(),
            'window_days' => 7,
        ]);

        $ingestAction = app(IngestTelematicsTickAction::class);
        $ingestAction->execute(
            device: $device,
            token: 'token-123',
            sequence: 1,
            recordedAt: now(),
            latitudeE6: -6200000,
            longitudeE6: 106816666,
            rpm: 3000,
            speedKmh: 60,
            coolantTempDeciC: 1250, // anomali suhu
            batteryMv: 9000,        // anomali aki drop
            fuelPermil: 700,
            odometerMeters: 100000
        );

        $count = DB::table('oto_telematics_anomalies')
            ->where('vehicle_id', $vehicleId)
            ->count();

        $this->assertSame(2, $count);
    }
}
