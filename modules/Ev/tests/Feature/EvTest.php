<?php

declare(strict_types=1);

namespace Modules\Ev\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Ev\Application\Services\EvChargingService;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvStation;
use Tests\TestCase;

class EvTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle;

    protected EvStation $station;

    protected EvCharger $charger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->vehicle = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 9999 EV',
            'brand' => 'Hyundai',
            'model' => 'Ioniq 5',
            'year' => 2024,
            'vin' => 'KMH12345678909999',
        ]);

        $this->station = EvStation::create([
            'station_code' => 'SPKLU-DUTA-01',
            'name' => 'Duta Mall Supercharger Hub',
            'location_type' => 'mall',
            'address' => 'Jl. A. Yani Km 2',
        ]);

        $this->charger = EvCharger::create([
            'station_id' => $this->station->id,
            'charger_code' => 'CHG-DC-01',
            'type' => 'DC_FAST',
            'max_kw' => 150,
            'status' => 'available',
        ]);

        // Seed wallet and revenue accounts
        LedgerAccount::create([
            'code' => "wallet:user:{$this->user->id}:IDR",
            'name' => "User {$this->user->id} Wallet",
            'asset_code' => 'IDR',
            'kind' => 'liability',
            'allow_negative' => true,
            'cached_balance' => '1000000',
        ]);

        LedgerAccount::create([
            'code' => 'oto:ev_revenue:IDR',
            'name' => 'EV Charging Revenue',
            'asset_code' => 'IDR',
            'kind' => 'revenue',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);
    }

    public function test_booking_conflict_is_rejected(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);

        $start = Carbon::parse('2026-10-07 14:00:00');
        $session1 = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, $start, 30);
        $this->assertNotNull($session1);

        $this->expectException(\InvalidArgumentException::class);
        // Overlapping slot
        $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, $start->copy()->addMinutes(15), 30);
    }

    public function test_charging_completion_bills_ledger_and_records_battery_twin(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);
        /** @var DigitalTwinInterface $twin */
        $twin = app(DigitalTwinInterface::class);

        $start = Carbon::parse('2026-10-07 15:00:00');
        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, $start, 30);

        // 40,000 Wh = 40 kWh => 40 * 2500 = 100,000 IDR
        $completedSession = $service->completeCharging($session, 40000, 97.5);

        $this->assertEquals('completed', $completedSession->status);
        $this->assertEquals(100000, $completedSession->total_cost_idr);

        // Verify battery twin hash-chain recorded
        $latestTwin = $twin->getLatestState('vehicle_battery', (string) $this->vehicle->id);
        $this->assertNotNull($latestTwin);
        $this->assertEquals(97.5, $latestTwin->state['soh_pct']);
        $this->assertTrue($twin->verifyChain('vehicle_battery', (string) $this->vehicle->id));
    }

    public function test_no_show_fee_posts_to_ledger(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);

        $start = Carbon::parse('2026-10-07 16:00:00');
        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, $start, 30);

        $noShowSession = $service->recordNoShow($session, 25000);

        $this->assertEquals('no_show', $noShowSession->status);
        $this->assertEquals(25000, $noShowSession->no_show_fee_idr);
    }
}
