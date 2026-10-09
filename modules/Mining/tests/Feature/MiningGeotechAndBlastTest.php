<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mining\Application\Services\MiningGeotechAndBlastService;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use RuntimeException;
use Tests\TestCase;

class MiningGeotechAndBlastTest extends TestCase
{
    use RefreshDatabase;

    protected MiningGeotechAndBlastService $service;

    protected MiningPit $pit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningGeotechAndBlastService::class);

        $site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-KALTIN-01',
            'name' => 'Kaltin Coal & Quarry Mining Complex',
            'commodity' => 'COAL',
            'location' => 'East Kalimantan',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'pit_code' => 'PIT-ALPHA-01',
            'name' => 'Pit Alpha Main Cut',
            'bench_level' => 120,
            'status' => 'ACTIVE',
        ]);

        // Register mining ledger accounts
        $accounts = [
            'min:marine_freight_expense:IDR' => 'expense',
            'min:marine_vendor_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Mining {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_119_2_and_119_6_a_blast_blocked_when_worker_inside_safety_exclusion_radius(): void
    {
        $blast = $this->service->scheduleBlast(
            pit: $this->pit,
            scheduledAt: now()->addHours(2)->toDateTimeString(),
            lat: -1.250000,
            lng: 116.850000,
            safetyRadiusMeters: 500.0,
            holes: 45,
            explosivesKg: 2500.0
        );

        $this->assertEquals('SCHEDULED', $blast->status);

        // 1. Worker located ~100m away (inside 500m exclusion zone) -> BLOCKED
        try {
            $this->service->detonateBlast($blast, [
                [
                    'worker_id' => 'WKR-OPERATOR-01',
                    'lat' => -1.250500,
                    'lng' => 116.850500,
                ],
            ]);
            $this->fail('Expected blast exclusion exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Blast detonation blocked', $e->getMessage());
        }

        $this->assertEquals('BLOCKED', $blast->refresh()->status);

        // 2. Clear zone: worker 1500m away -> DETONATED
        $blastClean = $this->service->scheduleBlast(
            pit: $this->pit,
            scheduledAt: now()->addHours(3)->toDateTimeString(),
            lat: -1.250000,
            lng: 116.850000,
            safetyRadiusMeters: 500.0,
            holes: 45,
            explosivesKg: 2500.0
        );

        $detonated = $this->service->detonateBlast($blastClean, [
            [
                'worker_id' => 'WKR-OPERATOR-SAFE',
                'lat' => -1.265000,
                'lng' => 116.865000,
            ],
        ]);

        $this->assertEquals('DETONATED', $detonated->status);
    }

    public function test_119_3_geotech_slope_stability_critical_shutdown_alarm(): void
    {
        $reading = $this->service->recordSlopeStability(
            sensorCode: 'SLOPE-RADAR-NORTH',
            pit: $this->pit,
            displacementMm: 65.0, // Critical (> 50mm)
            velocityMmDay: 14.2  // Critical (> 10mm/day)
        );

        $this->assertTrue($reading->evacuation_alarm_triggered);
        $this->assertEquals('RED_SHUTDOWN', $reading->alert_level);
    }

    public function test_119_5_and_119_6_e_barge_shipment_marine_logistics_settlement(): void
    {
        $barge = $this->service->dispatchBarge(
            vesselName: 'TB Nusantara Prima / BG Selat Makassar 300ft',
            destinationPort: 'Cigading Port, Banten',
            manifestTonnage: 7500.0,
            weighbridgeTonnage: 7480.0,
            freightTariffIdr: 450_000_000
        );

        $this->assertEquals('LOADED', $barge->status);
        $this->assertNotEmpty($barge->custody_hash);

        $freightExp = LedgerAccount::where('code', 'min:marine_freight_expense:IDR')->first();
        $vendorPayable = LedgerAccount::where('code', 'min:marine_vendor_payable:IDR')->first();

        $this->assertEquals('450000000', (string) $freightExp->cached_balance);
        $this->assertEquals('-450000000', (string) $vendorPayable->cached_balance);
    }
}
