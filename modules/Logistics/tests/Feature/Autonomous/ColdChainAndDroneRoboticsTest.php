<?php

namespace Modules\Logistics\tests\Feature\Autonomous;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Tests\TestCase;

class ColdChainAndDroneRoboticsTest extends TestCase
{
    use RefreshDatabase;

    protected AutonomousLogisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutonomousLogisticsService::class);

        LedgerAccount::create([
            'code' => 'lgx:carrier_payable:IDR',
            'name' => 'Logistics Carrier Payable',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'lgx:dispute_escrow:IDR',
            'name' => 'Carrier Dispute Escrow Hold',
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_80_1_temp_breach_holds_carrier_payment_only_when_exceeding_ten_minutes(): void
    {
        // 1. Minor breach under 10 minutes -> no hold
        $minor = $this->service->recordTemperatureCheck(
            shipmentCode: 'SHP-COLD-01',
            carrierId: 88,
            recordedTempC: 8.5,
            maxAllowedTempC: 4.0,
            durationMinutes: 8, // <= 10 mins grace
            carrierPayableIdr: 5_000_000
        );
        $this->assertNull($minor);

        // 2. Severe breach > 10 minutes -> holds carrier payout in ledger
        $breach = $this->service->recordTemperatureCheck(
            shipmentCode: 'SHP-COLD-02',
            carrierId: 88,
            recordedTempC: 11.2,
            maxAllowedTempC: 4.0,
            durationMinutes: 25, // > 10 mins
            carrierPayableIdr: 7_500_000
        );

        $this->assertNotNull($breach);
        $this->assertEquals('HELD', $breach->status);
        $this->assertEquals(7_500_000, $breach->hold_amount_idr);
        $this->assertNotNull($breach->hash_proof);

        // Check ledger balances
        $escrow = LedgerAccount::where('code', 'lgx:dispute_escrow:IDR')->first();
        $this->assertEquals('-7500000', (string) $escrow->cached_balance);

        // 3. Release hold after dispute resolution
        $released = $this->service->releaseBreachHold($breach);
        $this->assertEquals('RELEASED', $released->status);
        $this->assertEquals('0', (string) $escrow->refresh()->cached_balance);
    }

    public function test_80_3_and_80_4_drone_mission_dispatch_constraints_and_cryptographic_pod(): void
    {
        $drone = DroneUnit::create([
            'drone_code' => 'DRN-AIR-01',
            'model_name' => 'OctoCopter HeavyLift',
            'battery_percent' => 85,
            'max_payload_kg' => 4.5,
            'max_range_km' => 12.0,
            'status' => 'IDLE',
        ]);

        // 1. Mission exceeding max payload fails
        $this->expectException(\RuntimeException::class);
        $this->service->dispatchDroneMission(
            drone: $drone,
            shipmentCode: 'SHP-MED-OVERWEIGHT',
            packageWeightKg: 6.0, // > 4.5 kg
            distanceKm: 5.0,
            destinationGeoHash: 'qqs34xyz'
        );
    }

    public function test_80_3_and_80_4_successful_drone_dispatch_and_pod_completion(): void
    {
        $drone = DroneUnit::create([
            'drone_code' => 'DRN-AIR-02',
            'model_name' => 'OctoCopter Express',
            'battery_percent' => 90,
            'max_payload_kg' => 4.0,
            'max_range_km' => 15.0,
            'status' => 'IDLE',
        ]);

        // Dispatch valid mission
        $mission = $this->service->dispatchDroneMission(
            drone: $drone,
            shipmentCode: 'SHP-MED-099',
            packageWeightKg: 2.0,
            distanceKm: 6.0,
            destinationGeoHash: 'qqs99abc'
        );

        $this->assertEquals('DISPATCHED', $mission->status);
        $this->assertEquals('IN_MISSION', $drone->refresh()->status);

        // Complete delivery with POD
        $delivered = $this->service->completeDroneDelivery($mission, 'qqs99abc-dropzone-3');
        $this->assertEquals('DELIVERED', $delivered->status);
        $this->assertNotNull($delivered->pod_signature_hash);
        $this->assertEquals('IDLE', $drone->refresh()->status);
    }
}
