<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningOps;

use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Mining\Application\Actions\DispatchHaulTruckAction;
use Modules\Mining\Application\Actions\EvaluateContractorFuelAnomalyAction;
use Modules\Mining\Application\Actions\IngestEquipmentTelemetryAction;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningOpsBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fuel_anomaly_triggers_contractor_payment_hold_exactly_once(): void
    {
        $this->seed();
        (new MiningRbacSeeder)->run();

        $site = MiningSite::create([
            'site_code' => 'SITE-SANGATTA-01',
            'name' => 'Sangatta Site',
            'commodity' => 'COAL',
            'location' => 'Kalimantan Timur',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $equipment = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'TRK-202',
            'type' => 'HAUL_TRUCK',
            'model' => 'Komatsu HD785',
            'capacity_ton' => 100,
            'status' => 'available',
            'engine_hours' => 3000,
        ]);

        $partyId = 'CONT-BORNEO-99';

        $action = app(EvaluateContractorFuelAnomalyAction::class);

        // Historical data points from M3 prompt: ton-km (thousands) [10,20,30,40,50], liters [230,410,620,800,1010]
        // New point 35 (thousand ton-km) -> pred 711.5, actual 860, excess 148.5 L = 2087 bps > 1500 bps -> ANOMALY
        $result = $action->execute([
            'site_id' => $site->id,
            'equipment_id' => $equipment->id,
            'contractor_party_id' => $partyId,
            'shift_date' => '2026-11-02',
            'shift_code' => 'SHIFT_A',
            'historical_ton_km_thousands' => [10, 20, 30, 40, 50],
            'historical_fuel_liters' => [230, 410, 620, 800, 1010],
            'actual_ton_km_thousands' => 35,
            'actual_fuel_liters' => 860,
            'fuel_rate_per_liter_idr' => 15000,
        ]);

        $this->assertTrue($result['is_anomaly']);
        $this->assertGreaterThan(1500, $result['excess_bps']);
        $this->assertEquals(2087, $result['excess_bps']);

        // Assert Case was created with status payment_held
        $case = MiningFuelAnomalyCase::where('equipment_id', $equipment->id)
            ->whereDate('shift_date', '2026-11-02')
            ->first();

        $this->assertNotNull($case);
        $this->assertEquals('payment_held', $case->status->value);

        // Assert Ledger posting holds payment
        $holdAccount = LedgerAccount::where('code', 'min:contractor_payment_hold:IDR')->first();
        $this->assertNotNull($holdAccount);

        // Rerun evaluation for identical shift - must be idempotent and hold exactly once
        $action->execute([
            'site_id' => $site->id,
            'equipment_id' => $equipment->id,
            'contractor_party_id' => $partyId,
            'shift_date' => '2026-11-02',
            'shift_code' => 'SHIFT_A',
            'historical_ton_km_thousands' => [10, 20, 30, 40, 50],
            'historical_fuel_liters' => [230, 410, 620, 800, 1010],
            'actual_ton_km_thousands' => 35,
            'actual_fuel_liters' => 860,
            'fuel_rate_per_liter_idr' => 15000,
        ]);

        $this->assertEquals(1, MiningFuelAnomalyCase::where('equipment_id', $equipment->id)->count());
    }

    public function test_telemetry_threshold_triggers_predictive_maintenance_and_dispatch_rejects_unavailable_trucks(): void
    {
        $this->seed();
        (new MiningRbacSeeder)->run();

        $site = MiningSite::create([
            'site_code' => 'SITE-SANGATTA-02',
            'name' => 'Sangatta Site 2',
            'commodity' => 'COAL',
            'location' => 'Kalimantan Timur',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $pit = MiningPit::create([
            'site_id' => $site->id,
            'pit_code' => 'PIT-SANGATTA-B',
            'name' => 'Sangatta Pit B',
            'target_production_ton' => 50000,
            'actual_production_ton' => 0,
        ]);

        $truck = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'TRK-205',
            'type' => 'HAUL_TRUCK',
            'model' => 'CAT 777D',
            'capacity_ton' => 100,
            'status' => 'available',
            'engine_hours' => 5050, // > 5000 hours threshold
        ]);

        // Ingest telemetry with high engine hours and vibration
        $telemetryAction = app(IngestEquipmentTelemetryAction::class);
        $telemetryResult = $telemetryAction->execute([
            'equipment_id' => $truck->id,
            'sequence_number' => 1,
            'engine_hours_total' => 5050,
            'vibration_rms_scaled' => 1950, // > 1800 threshold
            'fuel_level_percent' => 80,
            'instant_fuel_rate_ml_s' => 120,
            'payload_kg' => 0,
            'latitude_scaled' => 100000,
            'longitude_scaled' => 200000,
        ]);

        $this->assertTrue($telemetryResult['maintenance_triggered']);
        $truck->refresh();
        $this->assertEquals('under_maintenance', $truck->status);

        // Attempt to dispatch unavailable truck -> must fail
        $dispatcher = User::factory()->create();
        $dispatcher->assignRole('dispatcher_mine');

        $dispatchAction = app(DispatchHaulTruckAction::class);

        $this->expectException(DomainException::class);
        $dispatchAction->execute([
            'site_id' => $site->id,
            'pit_id' => $pit->id,
            'truck_id' => $truck->id,
            'shift_date' => '2026-11-02',
            'shift_code' => 'SHIFT_A',
            'target_payload_kg' => 90000,
            'dispatcher_id' => $dispatcher->id,
        ]);
    }
}
