<?php

declare(strict_types=1);

namespace Modules\Ev\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Ev\Application\Services\BatteryPassportService;
use Modules\Ev\Application\Services\EvChargingService;
use Modules\Ev\Domain\Events\BatteryDegradedThresholdReached;
use Modules\Ev\Domain\Models\EvBatteryPassport;
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
            'transformer_capacity_kw' => 250,
        ]);

        $this->charger = EvCharger::create([
            'station_id' => $this->station->id,
            'charger_code' => 'CHG-DC-01',
            'type' => 'DC_FAST',
            'max_kw' => 150,
            'status' => 'available',
        ]);

        // Seed wallet and revenue accounts
        $this->seed(LedgerAccountsSeeder::class);
        $wallet = $this->user->walletAccount('IDR');
        $wallet->forceFill(['cached_balance' => '1000000'])->save();
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

        $revAccount = LedgerAccount::where('code', 'oto:ev_revenue:IDR')->firstOrFail();
        $feeEntry = LedgerEntry::where('account_id', $revAccount->id)->latest('id')->first();
        $this->assertNotNull($feeEntry);
        $this->assertEquals(25000, (int) $feeEntry->amount);
    }

    public function test_kwh_metering_matches_exact_ledger_revenue_billing(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);

        // Peak hour: 18:00 -> tariff Rp 3.000/kWh
        $peakStart = Carbon::parse('2026-10-07 18:00:00');
        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, $peakStart, 45);

        $this->assertEquals(3000, $session->tariff_per_kwh_idr);

        // 35,500 Wh = 35.5 kWh * 3000 = 106,500 IDR
        $completed = $service->completeCharging($session, 35500);

        $this->assertEquals(106500, $completed->total_cost_idr);
        $this->assertGreaterThan(0.0, $completed->scope2_co2_kg);
        $this->assertGreaterThan(0.0, $completed->green_km_saved_co2_kg);

        // Check revenue posting
        $revAccount = LedgerAccount::where('code', 'oto:ev_revenue:IDR')->firstOrFail();
        $revenueEntry = LedgerEntry::where('account_id', $revAccount->id)->latest('id')->first();

        $this->assertNotNull($revenueEntry);
        $this->assertEquals(106500, (int) $revenueEntry->amount);
    }

    public function test_battery_passport_hash_chain_records_soh_degradation_deterministically(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);
        /** @var BatteryPassportService $passportService */
        $passportService = app(BatteryPassportService::class);

        $start = Carbon::parse('2026-10-07 10:00:00');
        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, $start, 30);

        // Complete without manual SoH: electro-degradation will be calculated
        $completed = $service->completeCharging($session, 50000, null, 42.0);

        $this->assertLessThan(100.0, $completed->final_soh_pct);

        // Verify passport block
        $block = EvBatteryPassport::where('vehicle_id', $this->vehicle->id)
            ->where('session_code', $session->session_code)
            ->first();

        $this->assertNotNull($block);
        $this->assertEquals(1, $block->cycle_number);
        $this->assertEquals(hash('sha256', BatteryPassportService::GENESIS_HASH_PREFIX.$this->vehicle->id), $block->prev_hash);
        $this->assertNotEmpty($block->block_hash);

        // Verify chain integrity
        $chainResult = $passportService->verifyChain($this->vehicle->id);
        $this->assertTrue($chainResult['valid']);
        $this->assertEquals(1, $chainResult['blocks_checked']);

        // Mutant test: alter block hash and assert verification fails
        $originalHash = $block->block_hash;
        $block->block_hash = 'corrupted_hash_value_1234567890abcdef1234567890abcdef1234567890abcd';
        $block->save();

        $tamperedResult = $passportService->verifyChain($this->vehicle->id);
        $this->assertFalse($tamperedResult['valid']);
        $this->assertEquals('Block hash corrupted', $tamperedResult['reason']);

        // Restore
        $block->block_hash = $originalHash;
        $block->save();
    }

    public function test_critical_soh_threshold_dispatches_trade_in_event(): void
    {
        Event::fake([BatteryDegradedThresholdReached::class]);

        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);

        // Create vehicle with low initial SoH
        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, Carbon::now(), 30);
        $session->initial_soh_pct = 70.02;
        $session->save();

        // High temperature & energy to drop below 70.0%
        $service->completeCharging($session, 60000, 69.8, 48.0);

        Event::assertDispatched(BatteryDegradedThresholdReached::class, function ($event) {
            return $event->vehicleId === $this->vehicle->id
                && $event->currentSohPct < 70.0
                && $event->recommendation === 'trade_in_autodex_hodl_to_drive';
        });
    }

    public function test_check_in_via_qr_transitions_session_to_plugged_in(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);

        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, Carbon::now(), 30);

        // Valid QR check-in
        $checkedIn = $service->checkInQr($session, $this->charger->charger_code);
        $this->assertEquals('plugged_in', $checkedIn->status);
        $this->assertNotNull($checkedIn->plugged_in_at);

        // Invalid QR check-in
        $this->expectException(\InvalidArgumentException::class);
        $service->checkInQr($session, 'INVALID-QR-CODE');
    }

    public function test_grid_load_balancing_defers_non_critical_session_without_cancellation(): void
    {
        /** @var EvChargingService $service */
        $service = app(EvChargingService::class);

        // Set up overload: charger 1 = 150 kW, charger 2 = 150 kW on 250 kW transformer = 300 kW (120% > 80%)
        $charger2 = EvCharger::create([
            'station_id' => $this->station->id,
            'charger_code' => 'CHG-DC-02',
            'type' => 'DC_FAST',
            'max_kw' => 150,
            'status' => 'charging',
        ]);
        $this->charger->status = 'charging';
        $this->charger->save();

        $session = $service->reserveSlot($this->charger->id, $this->vehicle->id, $this->user->id, Carbon::now(), 30);
        $initialReservedTo = $session->reserved_to ? $session->reserved_to->copy() : Carbon::now()->addMinutes(30);

        $result = $service->simulateGridLoadBalancing($this->station->id);

        $this->assertTrue($result['is_peak_overload']);
        $this->assertGreaterThanOrEqual(1, $result['deferred_sessions_count']);

        $session->refresh();
        $this->assertEquals(15, $session->deferred_minutes);
        $this->assertEquals('reserved', $session->status); // NOT cancelled!
        $this->assertNotEquals('cancelled', $session->status);
    }
}
