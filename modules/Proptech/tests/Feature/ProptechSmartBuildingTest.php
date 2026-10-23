<?php

declare(strict_types=1);

namespace Modules\Proptech\tests\Feature;

use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
            'current_kwh_rate' => 1800, // IDR 1,800/kWh (A3 integer money)
            'baseline_kwh_per_hour' => 10.0,
            'status' => 'ACTIVE',
        ]);

        $this->seed(LedgerAccountsSeeder::class);
    }

    /**
     * 76.1 & 76.7 (d) Ingest sensor idempoten & update okupansi/status online
     */
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

        $this->assertSame(75, $this->zone->refresh()->current_occupancy);
        $this->assertTrue($sensor1->is_online);

        // Ingest reading 2 (identical / duplicate) -> idempotent, returns same instance
        $sensor2 = $this->proptechService->ingestSensorReading(
            zone: $this->zone,
            sensorCode: 'CCTV-OCC-01',
            sensorType: 'OCCUPANCY',
            readingValue: 75.0,
            recordedAt: $now
        );

        $this->assertSame($sensor1->id, $sensor2->id);
        $this->assertSame(1, BuildingSensor::where('sensor_code', 'CCTV-OCC-01')->count());
    }

    /**
     * 76.2 & 76.7 (a) Otomasi HVAC & pencahayaan rule engine deterministik memicu perintah tepat 1x
     */
    public function test_76_2_deterministic_hvac_automation_rules(): void
    {
        // 1. High occupancy (> 50) -> trigger SET_TEMP to 22.0
        $this->zone->update(['current_occupancy' => 65]);
        $cmdHigh = $this->proptechService->evaluateAutomationRules($this->zone);

        $this->assertNotNull($cmdHigh);
        $this->assertSame('SET_TEMP', $cmdHigh->command_type);
        $this->assertEquals(22.0, (float) $cmdHigh->target_value);

        // 2. Zero occupancy -> trigger SETBACK to 27.0 with kWh savings
        $this->zone->update(['current_occupancy' => 0]);
        $cmdEmpty = $this->proptechService->evaluateAutomationRules($this->zone);

        $this->assertNotNull($cmdEmpty);
        $this->assertSame('SETBACK', $cmdEmpty->command_type);
        $this->assertEquals(27.0, (float) $cmdEmpty->target_value);
        $this->assertEquals(15.5, (float) $cmdEmpty->estimated_kwh_saved);
    }

    /**
     * 76.3, 76.4 & 76.7 (b)(c)(e) Tagihan utilitas per zona aktual, Overtime AC, GRK real-time & Ledger
     */
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

        // Overtime AC: 2 hours @ Rp 75.000 = Rp 150.000
        $billing = $this->proptechService->generateZoneUtilityBilling(
            zone: $this->zone,
            tenantId: 42,
            billingDate: $date,
            overtimeAcHours: 2
        );

        $this->assertEquals(200.0, (float) $billing->total_kwh);
        $this->assertSame(2, $billing->overtime_ac_hours);
        $this->assertSame(150_000, $billing->overtime_ac_amount_idr);
        // Rate is 1800 IDR/kWh -> 200 * 1800 = 360,000 IDR base
        $this->assertSame(360_000, $billing->base_amount_idr);
        // Total = 360,000 + 150,000 = 510,000 IDR
        $this->assertSame(510_000, $billing->total_amount_idr);
        // GHG emissions: 200 * 0.85 = 170.0 kg CO2e
        $this->assertEquals(170.0, (float) $billing->carbon_emission_kg);
        $this->assertSame('BILLED', $billing->status);

        // Verify ledger posting balances: AR -510k, Revenue +510k
        $ar = LedgerAccount::where('code', 'prp:utility_ar:IDR')->first();
        $rev = LedgerAccount::where('code', 'prp:utility_rev:IDR')->first();
        $this->assertSame('-510000', (string) $ar->cached_balance);
        $this->assertSame('510000', (string) $rev->cached_balance);

        // Reconcile via smart building audit command (should be exit code 0)
        $auditCode = Artisan::call('prp:audit-smartbuilding');
        $this->assertSame(0, $auditCode);
    }

    /**
     * 76.5 Prescriptive operations: proposal, approval, application
     */
    public function test_76_5_prescriptive_operations_workflow(): void
    {
        $op = $this->proptechService->createPrescriptiveRecommendation(
            zone: $this->zone,
            actionCode: 'PRESC-SETBACK-13-15',
            recommendationText: 'Setback suhu zona 27C pada jam istirahat 13:00-15:00',
            potentialKwhSaved: 30.0,
            paybackMonths: 1
        );

        $this->assertSame('PROPOSED', $op->status);
        $this->assertSame(1620000, $op->potential_saving_idr); // 30 kWh * 1800 * 30 days

        // Approve recommendation
        $approved = $this->proptechService->approvePrescriptiveRecommendation($op);
        $this->assertSame('APPROVED', $approved->status);

        // Apply recommendation
        $applied = $this->proptechService->applyPrescriptiveRecommendation($approved);
        $this->assertSame('APPLIED', $applied->status);
    }
}
