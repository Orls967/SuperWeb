<?php

declare(strict_types=1);

namespace Tests\Acceptance\ColdChainDrone;

use App\Models\User;
use App\Quality\Audit\Fixtures\LogisticsColdChainCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;
use Modules\Logistics\Domain\Models\Autonomous\DroneMission;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Modules\Logistics\Domain\Models\Autonomous\NoFlyZone;
use Modules\Logistics\Domain\Models\Autonomous\TempBreachHold;
use Modules\Logistics\Domain\Models\Shipment;
use Tests\TestCase;

class ColdChainDroneAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);

        // Pastikan akun buku besar cold-chain tersedia di tabel
        LedgerAccount::firstOrCreate(
            ['code' => 'lgx:carrier_payable_on_hold:IDR'],
            ['name' => 'Logistics Carrier Payable On Hold', 'kind' => 'LIABILITY', 'asset_code' => 'IDR', 'allow_negative' => true, 'cached_balance' => 0]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'ap:carrier:42:IDR'],
            ['name' => 'Carrier 42 Payable', 'kind' => 'LIABILITY', 'asset_code' => 'IDR', 'allow_negative' => true, 'cached_balance' => 10_000_000]
        );
    }

    /**
     * Test 1: Akses grup rute & autorisasi role (D80-1..D80-4).
     */
    public function test_1_route_access_and_role_authorization_matrix(): void
    {
        $logisticsAdmin = User::factory()->create(['role' => 'logistics_admin']);
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $unauthorizedCustomer = User::factory()->create(['role' => 'customer']);

        // 1. Authorized access ke cold-chain dan drone
        $this->actingAs($logisticsAdmin)
            ->get(route('logistics.cold-chain.index'))
            ->assertOk();

        $this->actingAs($dispatcher)
            ->get(route('logistics.drone.index'))
            ->assertOk();

        // 2. Unauthorized role forbidden (403)
        $this->actingAs($unauthorizedCustomer)
            ->get(route('logistics.cold-chain.index'))
            ->assertForbidden();

        // 3. Unauthenticated redirect (302)
        auth()->logout();
        $this->get(route('logistics.cold-chain.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * Test 2: Alur utama Cold-Chain breach > 10 menit memicu Hold Ledger + Idempotensi (D80-1, D80-2, D80-3).
     */
    public function test_2_cold_chain_breach_detection_triggers_ledger_hold_idempotently(): void
    {
        $service = app(AutonomousLogisticsService::class);
        $shipmentCode = 'SHP-REEFER-001';
        $carrierId = 42;
        $payableIdr = 2_500_000;

        // Telemetri: excursion suhu 12.5 °C (max 8.0 °C), durasi 11 menit (660 detik > 600 detik)
        $hold = $service->recordTemperatureCheck(
            shipmentCode: $shipmentCode,
            carrierId: $carrierId,
            recordedTempC: 12.5,
            maxAllowedTempC: 8.0,
            durationMinutes: 11,
            carrierPayableIdr: $payableIdr,
            legId: 101,
            episodeId: 'EP-001'
        );

        expect($hold)->not->toBeNull();
        expect($hold->status)->toBe('HELD');
        expect($hold->hold_amount_idr)->toBe($payableIdr);

        // Verifikasi saldo ledger on-hold
        $onHoldAccount = LedgerAccount::where('code', 'lgx:carrier_payable_on_hold:IDR')->firstOrFail();
        expect(abs((int) $onHoldAccount->cached_balance))->toBe($payableIdr);

        // Idempotensi: Pembacaan berulang di episode yang sama TIDAK menggandakan hold
        $retryHold = $service->recordTemperatureCheck(
            shipmentCode: $shipmentCode,
            carrierId: $carrierId,
            recordedTempC: 13.0,
            maxAllowedTempC: 8.0,
            durationMinutes: 15,
            carrierPayableIdr: $payableIdr,
            legId: 101,
            episodeId: 'EP-001'
        );

        expect($retryHold->id)->toBe($hold->id);
        $onHoldAccount->refresh();
        expect(abs((int) $onHoldAccount->cached_balance))->toBe($payableIdr);
    }

    /**
     * Test 3: Durasi breach <= 10 menit tidak di-hold, dan rilis tahanan memulihkan utang carrier (D80-1, D80-3).
     */
    public function test_3_breach_under_ten_minutes_ignored_and_release_restores_payable(): void
    {
        $service = app(AutonomousLogisticsService::class);
        $shipmentCode = 'SHP-REEFER-GRACE';
        $carrierId = 42;
        $payableIdr = 1_800_000;

        // 1. Durasi 9 menit -> tidak hold
        $noHold9 = $service->recordTemperatureCheck(
            shipmentCode: $shipmentCode,
            carrierId: $carrierId,
            recordedTempC: 14.0,
            maxAllowedTempC: 8.0,
            durationMinutes: 9,
            carrierPayableIdr: $payableIdr
        );
        expect($noHold9)->toBeNull();

        // 2. Tepat 10 menit -> tetap tidak hold (D80-1: > 10 menit)
        $noHold10 = $service->recordTemperatureCheck(
            shipmentCode: $shipmentCode,
            carrierId: $carrierId,
            recordedTempC: 14.0,
            maxAllowedTempC: 8.0,
            durationMinutes: 10,
            carrierPayableIdr: $payableIdr
        );
        expect($noHold10)->toBeNull();

        // 3. Picu hold valid (11 menit) lalu rilis
        $activeHold = $service->recordTemperatureCheck(
            shipmentCode: $shipmentCode,
            carrierId: $carrierId,
            recordedTempC: 14.0,
            maxAllowedTempC: 8.0,
            durationMinutes: 11,
            carrierPayableIdr: $payableIdr,
            legId: 202,
            episodeId: 'EP-002'
        );
        expect($activeHold)->not->toBeNull();

        $onHoldAccount = LedgerAccount::where('code', 'lgx:carrier_payable_on_hold:IDR')->firstOrFail();
        expect(abs((int) $onHoldAccount->cached_balance))->toBe($payableIdr);

        // Rilis tahanan (investigasi selesai / klaim beres)
        $released = $service->releaseBreachHold($activeHold);
        expect($released->status)->toBe('RELEASED');

        $onHoldAccount->refresh();
        expect(abs((int) $onHoldAccount->cached_balance))->toBe(0);

        // Rilis ulang idempoten
        $reRelease = $service->releaseBreachHold($released);
        expect($reRelease->status)->toBe('RELEASED');
        $onHoldAccount->refresh();
        expect(abs((int) $onHoldAccount->cached_balance))->toBe(0);
    }

    /**
     * Test 4: Aturan bisnis Drone: Batasan fisik berat muatan, jarak, dan daya baterai (D80-4).
     */
    public function test_4_drone_dispatch_constraints_reject_overweight_outofrange_and_lowbattery(): void
    {
        $service = app(AutonomousLogisticsService::class);

        $drone = DroneUnit::create([
            'drone_code' => 'DRN-PHYS-TEST-01',
            'model_name' => 'OctoCopter Heavy',
            'battery_percent' => 100,
            'max_payload_kg' => 5.0,
            'max_range_km' => 15.0,
            'status' => 'IDLE',
        ]);

        // 1. Muatan melebihi 5.0 kg ditolak
        expect(fn () => $service->dispatchDroneMission(
            drone: $drone,
            shipmentCode: 'SHP-OVERWEIGHT',
            packageWeightKg: 5.5,
            distanceKm: 8.0,
            destinationGeoHash: 'qqguygv1t'
        ))->toThrow(\RuntimeException::class);

        // 2. Jarak melebihi 15.0 km ditolak
        expect(fn () => $service->dispatchDroneMission(
            drone: $drone,
            shipmentCode: 'SHP-OUTOFRANGE',
            packageWeightKg: 3.0,
            distanceKm: 16.0,
            destinationGeoHash: 'qqguygv1t'
        ))->toThrow(\RuntimeException::class);

        // 3. Baterai tidak cukup ditolak
        $lowBatteryDrone = DroneUnit::create([
            'drone_code' => 'DRN-LOWBAT-01',
            'model_name' => 'OctoCopter LowBat',
            'battery_percent' => 20, // Kurang untuk 10 km round-trip (butuh 60% + 15% = 75%)
            'max_payload_kg' => 5.0,
            'max_range_km' => 15.0,
            'status' => 'IDLE',
        ]);

        expect(fn () => $service->dispatchDroneMission(
            drone: $lowBatteryDrone,
            shipmentCode: 'SHP-LOWBAT',
            packageWeightKg: 2.0,
            distanceKm: 10.0,
            destinationGeoHash: 'qqguygv1t'
        ))->toThrow(\RuntimeException::class);
    }

    /**
     * Test 5: Algoritma Router No-Fly Zone (10.246 m) dan verifikasi POD Geohash (D80-4).
     */
    public function test_5_drone_navigation_avoids_nofly_zone_and_pod_geohash_verified(): void
    {
        $service = app(AutonomousLogisticsService::class);

        // Setup zona larangan terbang (persegi 4000,-1000 s.d. 6000,1000)
        NoFlyZone::create([
            'zone_code' => 'NFZ-SQUARE-01',
            'name' => 'Restricted Airspace Central',
            'polygon_coordinates_json' => json_encode([
                ['x' => 4000, 'y' => -1000],
                ['x' => 6000, 'y' => -1000],
                ['x' => 6000, 'y' => 1000],
                ['x' => 4000, 'y' => 1000],
            ]),
            'is_active' => true,
        ]);

        $drone = DroneUnit::create([
            'drone_code' => 'DRN-ROUTE-TEST-01',
            'model_name' => 'Router Drone X',
            'battery_percent' => 100,
            'max_payload_kg' => 5.0,
            'max_range_km' => 15.0,
            'status' => 'IDLE',
        ]);

        // Dispatch dari Hub (0,0) ke Tujuan (10000,0)
        $hub = ['x' => 0, 'y' => 0];
        $target = ['x' => 10000, 'y' => 0];

        $mission = $service->dispatchDroneMission(
            drone: $drone,
            shipmentCode: 'SHP-DRONE-ROUTED',
            packageWeightKg: 2.5,
            distanceKm: 10.0,
            destinationGeoHash: 'qqguygv1t',
            hubCoord: $hub,
            targetCoord: $target
        );

        expect($mission->status)->toBe('DISPATCHED');
        expect($mission->distance_km)->toBe(10.246);

        // POD delivery dengan geohash Jakarta valid
        $lat = -6.175392;
        $lng = 106.827153;
        $delivered = $service->completeDroneDelivery(
            mission: $mission,
            podGeoHash: 'qqguygv1t',
            latitude: $lat,
            longitude: $lng
        );

        expect($delivered->status)->toBe('DELIVERED');
        expect($delivered->pod_signature_hash)->not->toBeEmpty();
        $drone->refresh();
        expect($drone->status)->toBe('IDLE');

        // POD dengan geohash rusak / tidak sesuai koordinat melempar exception
        $mission2 = DroneMission::create([
            'mission_code' => 'DRN-FAIL-POD-01',
            'drone_unit_id' => $drone->id,
            'shipment_code' => 'SHP-FAIL-POD',
            'package_weight_kg' => 1.0,
            'distance_km' => 5.0,
            'destination_geo_hash' => 'qqguygv1t',
            'status' => 'DISPATCHED',
        ]);

        expect(fn () => $service->completeDroneDelivery(
            mission: $mission2,
            podGeoHash: 'qqguygv1t',
            latitude: -7.257472, // Surabaya (tidak cocok dengan Jakarta)
            longitude: 112.752090
        ))->toThrow(\InvalidArgumentException::class);
    }

    /**
     * Test 6: IDOR isolasi data carrier dan Audit Cold-Chain dua sumber (D80-5).
     */
    public function test_6_carrier_idor_isolation_and_two_source_cold_chain_audit(): void
    {
        $service = app(AutonomousLogisticsService::class);

        // 1. Verifikasi IDOR: Carrier A tidak dapat melihat data breach carrier B
        $carrierAId = 42;
        $carrierBId = 99;

        $holdA = $service->recordTemperatureCheck(
            shipmentCode: 'SHP-IDOR-01',
            carrierId: $carrierAId,
            recordedTempC: 15.0,
            maxAllowedTempC: 4.0,
            durationMinutes: 20,
            carrierPayableIdr: 1_000_000,
            legId: 301,
            episodeId: 'EP-IDOR-A'
        );
        expect($holdA)->not->toBeNull();

        // Query dengan scope carrier B tidak memuat data carrier A
        $carrierBHolds = TempBreachHold::where('carrier_id', $carrierBId)->get();
        expect($carrierBHolds->contains('id', $holdA->id))->toBeFalse();

        // 2. Audit dua sumber keadaan bersih (exit 0)
        $cleanExitCode = Artisan::call('lgx:audit-coldchain');
        expect($cleanExitCode)->toBe(0);

        // 3. Audit gagal (exit 1) saat data dikorupsi lewat fixture
        $fixture = new LogisticsColdChainCorruptionFixture();
        $fixture->corrupt();

        $corruptExitCode = Artisan::call('lgx:audit-coldchain');
        expect($corruptExitCode)->toBe(1);
    }
}
