<?php

namespace Modules\Proptech\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Tests\TestCase;

class ProptechSmartBuildingTest extends TestCase
{
    use RefreshDatabase;

    protected ProptechService $proptechService;

    protected BuildingZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proptechService = app(ProptechService::class);

        $this->zone = BuildingZone::create([
            'zone_code' => 'ZN-DUTA-L2-01',
            'building_code' => 'BLD-DUTA-01',
            'name' => 'Food Court Zone A',
            'floor_level' => 'L2',
            'area_sqm' => 450.0,
            'current_temp_c' => 24.5,
            'current_occupancy' => 10,
            'current_kwh_rate' => 1800.0, // IDR 1,800/kWh
        ]);

        LedgerAccount::create([
            'code' => 'prp:utility_ar:IDR',
            'name' => 'Proptech Utility Receivable',
            'asset_code' => 'IDR',
            'kind' => 'asset',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        LedgerAccount::create([
            'code' => 'prp:utility_rev:IDR',
            'name' => 'Proptech Utility Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_76_1_sensor_ingest_idempotency_and_occupancy_update(): void
    {
        $now = Carbon::parse('2026-10-10 12:00:00');

        // Ingest reading 1
        $sensor1 = $this->proptechService->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'CCTV-OCC-01',
            sensorType: 'OCCUPANCY',
            readingValue: 75.0,
            recordedAt: $now
        );

        $this->assertEquals(75, $this->zone->refresh()->current_occupancy);

        // Ingest reading 2 (identical / duplicate) -> idempotent, returns same instance
        $sensor2 = $this->proptechService->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'CCTV-OCC-01',
            sensorType: 'OCCUPANCY',
            readingValue: 75.0,
            recordedAt: $now
        );

        $this->assertEquals($sensor1->id, $sensor2->id);
        $this->assertEquals(1, BuildingSensor::where('sensor_code', 'CCTV-OCC-01')->count());
    }

    public function test_76_2_deterministic_hvac_automation_rules(): void
    {
        // 1. High occupancy (> 50) -> trigger SET_TEMP to 22.0
        $this->zone->update(['current_occupancy' => 65]);
        $cmdHigh = $this->proptechService->evaluateAutomationRules($this->zone);

        $this->assertNotNull($cmdHigh);
        $this->assertEquals('SET_TEMP', $cmdHigh->command_type);
        $this->assertEquals(22.0, (float) $cmdHigh->target_value);

        // 2. Zero occupancy -> trigger SETBACK to 27.0 with kWh savings
        $this->zone->update(['current_occupancy' => 0]);
        $cmdEmpty = $this->proptechService->evaluateAutomationRules($this->zone);

        $this->assertNotNull($cmdEmpty);
        $this->assertEquals('SETBACK', $cmdEmpty->command_type);
        $this->assertEquals(27.0, (float) $cmdEmpty->target_value);
        $this->assertEquals(15.5, (float) $cmdEmpty->estimated_kwh_saved);
    }

    public function test_76_3_and_76_4_zone_utility_billing_and_ghg_emissions_with_ledger(): void
    {
        $date = Carbon::parse('2026-10-10');

        // Record power readings: 120 kWh + 80 kWh = 200 kWh
        $this->proptechService->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'MTR-PWR-01',
            sensorType: 'POWER_KWH',
            readingValue: 120.0,
            recordedAt: Carbon::parse('2026-10-10 08:00:00')
        );

        $this->proptechService->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'MTR-PWR-01',
            sensorType: 'POWER_KWH',
            readingValue: 80.0,
            recordedAt: Carbon::parse('2026-10-10 16:00:00')
        );

        $billing = $this->proptechService->generateZoneUtilityBilling(
            zone: $this->zone,
            tenantId: 42,
            billingDate: $date
        );

        $this->assertEquals(200.0, (float) $billing->total_kwh);
        // Rate is 1800 IDR/kWh -> 200 * 1800 = 360,000 IDR
        $this->assertEquals(360_000, $billing->total_amount_idr);
        // GHG emissions: 200 * 0.85 = 170.0 kg CO2e
        $this->assertEquals(170.0, (float) $billing->carbon_emission_kg);
        $this->assertEquals('BILLED', $billing->status);

        // Verify ledger posting balances: AR +360k, Revenue -360k
        $ar = LedgerAccount::where('code', 'prp:utility_ar:IDR')->first();
        $rev = LedgerAccount::where('code', 'prp:utility_rev:IDR')->first();
        $this->assertEquals('360000', (string) $ar->cached_balance);
        $this->assertEquals('-360000', (string) $rev->cached_balance);
    }
}
