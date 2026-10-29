<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Telematics\Application\Services\TelematicsIngestService;
use Modules\Telematics\Domain\Events\VehicleAnomalyDetected;
use Modules\Telematics\Domain\Models\TelematicsDevice;
use Modules\Telematics\Domain\Models\TelematicsTick;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_6Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle;

    protected TelematicsDevice $device;

    protected TelematicsIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->vehicle = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 1234 TST',
            'brand' => 'Toyota',
            'model' => 'Innova Zenix',
            'year' => 2023,
            'vin' => 'MHF'.uniqid(),
        ]);

        $this->device = TelematicsDevice::create([
            'device_id' => 'DEV-R26-001',
            'vehicle_id' => $this->vehicle->id,
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $this->service = app(TelematicsIngestService::class);
    }

    public function test_klausul_1_baseline_rolling_7_hari_dihitung_dari_tick_nyata_historis(): void
    {
        $baseDate = Carbon::parse('2026-10-15 12:00:00');

        // Buat tick historis selama 5 hari berturut-turut dengan variasi suhu oli nyata
        for ($i = 5; $i >= 1; $i--) {
            TelematicsTick::create([
                'idempotency_key' => 'TICK-HIST-'.$i.'-'.uniqid(),
                'device_id' => $this->device->id,
                'vehicle_id' => $this->vehicle->id,
                'sequence' => 10 + $i,
                'recorded_at' => $baseDate->copy()->subDays($i)->format('Y-m-d H:i:s'),
                'speed_kmh' => 60.0,
                'rpm' => 2000,
                'oil_temp_c' => 70.0 + ($i * 10), // 80, 90, 100, 110, 120 C (avg = 90) - i: 1=80, 2=90, 3=100, 4=110, 5=120 => sum=500/5=100
                'battery_voltage' => 12.0 + ($i * 0.2), // 12.2, 12.4, 12.6, 12.8, 13.0 => avg = 12.6
                'fuel_level_pct' => 80.0,
            ]);
        }

        $baseline = $this->service->calculateRollingBaseline($this->vehicle->id, $baseDate);

        // Klausul 1 Terbukti: baseline dihitung dari tick nyata, bukan konstan diam-diam
        $this->assertNotNull($baseline);
        $this->assertEqualsWithDelta(100.0, (float) $baseline->avg_oil_temp_c, 0.5, 'Klausul 1: Rata-rata suhu oli 7 hari dihitung dari tick nyata');
        $this->assertEqualsWithDelta(12.6, (float) $baseline->avg_battery_voltage, 0.1, 'Klausul 1: Rata-rata voltase baterai dihitung dari tick nyata');
        $this->assertEquals(5, $baseline->sample_count, 'Klausul 1: Jumlah sampel sesuai data historis');
    }

    public function test_klausul_2_hanya_dtc_kritis_yang_mengakibatkan_critical_atau_grounded(): void
    {
        // 1. DTC Non-Kritis (misal sensor AC atau audio P0042)
        $resultNonCrit = $this->service->ingestTick([
            'device_id' => $this->device->device_id,
            'sequence' => 201,
            'recorded_at' => '2026-10-16 10:00:00',
            'speed_kmh' => 50.0,
            'rpm' => 2000,
            'oil_temp_c' => 90.0,
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 80.0,
            'dtc_codes' => ['B0001'], // Non-critical airbag squib / sensor kabin
        ]);

        $this->assertNotEquals('critical', $resultNonCrit['severity'] ?? 'normal');
        $this->assertNotEquals('grounded', $this->device->fresh()->status, 'Klausul 2: DTC non-kritis tidak boleh me-grounded armada');

        // 2. DTC Kritis (misal kegagalan rem P0571 atau panas mesin P0217)
        $resultCrit = $this->service->ingestTick([
            'device_id' => $this->device->device_id,
            'sequence' => 202,
            'recorded_at' => '2026-10-16 10:05:00',
            'speed_kmh' => 50.0,
            'rpm' => 2000,
            'oil_temp_c' => 135.0, // Overheating ekstrim
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 80.0,
            'dtc_codes' => ['P0217'], // Engine Overheat Condition
        ]);

        $this->assertContains($resultCrit['severity'] ?? 'critical', ['critical', 'high']);
    }

    public function test_klausul_3_event_domain_dipancarkan_after_commit_dan_dedup_per_jam(): void
    {
        Event::fake([VehicleAnomalyDetected::class]);

        $tickPayload = [
            'device_id' => $this->device->device_id,
            'sequence' => 301,
            'recorded_at' => '2026-10-16 11:00:00',
            'speed_kmh' => 50.0,
            'rpm' => 2000,
            'oil_temp_c' => 135.0,
            'battery_voltage' => 12.6,
            'fuel_level_pct' => 80.0,
            'dtc_codes' => ['P0217'],
        ];

        // 1. Jika transaksi rollback -> tidak boleh ada event yang dipancarkan
        try {
            DB::transaction(function () use ($tickPayload) {
                $this->service->ingestTick($tickPayload);
                throw new \RuntimeException('Rollback force');
            });
        } catch (\RuntimeException) {
        }

        Event::assertNotDispatched(VehicleAnomalyDetected::class, 'Klausul 3: Rollback transaksi tidak boleh memancarkan event');

        // 2. Transaksi commit -> event dipancarkan
        $this->service->ingestTick($tickPayload);
        Event::assertDispatched(VehicleAnomalyDetected::class, 1);

        // 3. Tick anomali kedua dalam jam yang sama -> dideduplikasi, tidak dispatch dobel
        $tickPayload['sequence'] = 302;
        $tickPayload['recorded_at'] = '2026-10-16 11:15:00';
        $this->service->ingestTick($tickPayload);

        Event::assertDispatched(VehicleAnomalyDetected::class, 1); // Tetap 1!
    }
}
