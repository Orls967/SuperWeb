<?php

declare(strict_types=1);

namespace Modules\Pricing\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pricing\Application\Services\AlgorithmicSurgePricingService;
use Modules\Pricing\Domain\Models\FrozenQuote;
use Modules\Pricing\Domain\Models\PriceTick;
use Modules\Pricing\Domain\Models\PriceTickAggregate;
use Modules\Pricing\Domain\Models\PriceWaterfallLog;
use Tests\TestCase;

class AlgorithmicSurgePricingTest extends TestCase
{
    use RefreshDatabase;

    protected AlgorithmicSurgePricingService $pricingService;

    protected User $admin;

    protected User $procurementUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->pricingService = app(AlgorithmicSurgePricingService::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->procurementUser = User::where('role', 'procurement')->first()
            ?? User::factory()->create(['role' => 'procurement']);
    }

    public function test_81_3_strict_floor_and_ceiling_guardrails(): void
    {
        $hpp = 100_000;
        $het = 180_000;

        // 1. Extreme low demand index 0.2 -> raw price = 24k -> clamped to floor (115k)
        $low = $this->pricingService->computeTickPrice(
            sku: 'PART-BRAKE-01',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 0.2
        );
        $this->assertEquals(115_000, $low['effective_price_idr']);
        $this->assertEquals(115_000, $low['floor_price_idr']);

        // 2. Extreme high demand index 3.0 -> raw price = 360k -> clamped to ceiling (180k)
        $high = $this->pricingService->computeTickPrice(
            sku: 'PART-BRAKE-01',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 3.0
        );
        $this->assertEquals(180_000, $high['effective_price_idr']);
        $this->assertEquals(180_000, $high['ceiling_price_idr']);
        $this->assertTrue($high['is_surge']);
    }

    public function test_81_3_daily_band_limits_curb_excessive_volatility(): void
    {
        $hpp = 100_000;
        $het = 300_000;
        $prevClose = 120_000; // Daily band ±35% -> min 78_000 (overridden by 115k floor), max 162_000

        $result = $this->pricingService->computeTickPrice(
            sku: 'PART-VOLATILE-01',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 2.5, // raw price = 300_000
            previousCloseIdr: $prevClose
        );

        $this->assertEquals(162_000, $result['effective_price_idr']);
        $this->assertEquals(162_000, $result['ceiling_price_idr']);
    }

    public function test_81_3_b2b_contract_price_always_beats_dynamic_surge(): void
    {
        $hpp = 100_000;
        $het = 250_000;
        $contractPrice = 135_000;

        // Even with huge surge demand (demandIndex 2.5), contract price remains fixed
        $result = $this->pricingService->computeTickPrice(
            sku: 'PART-FILTER-02',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 2.5,
            contractPriceIdr: $contractPrice
        );

        $this->assertEquals(135_000, $result['effective_price_idr']);
        $this->assertEquals('CONTRACT_PRICE', $result['source']);
        $this->assertFalse($result['is_surge']);
    }

    public function test_81_3_waterfall_audit_trail_recorded_on_compute(): void
    {
        $hpp = 100_000;
        $het = 150_000;

        $result = $this->pricingService->computeTickPrice(
            sku: 'PART-WATERFALL-01',
            baseHppIdr: $hpp,
            hetCeilingIdr: $het,
            demandIndex: 1.8,
            channel: 'STORE_B2C'
        );

        $this->assertNotNull($result['waterfall_id']);
        $log = PriceWaterfallLog::find($result['waterfall_id']);
        $this->assertNotNull($log);
        $this->assertEquals('PART-WATERFALL-01', $log->sku);
        $this->assertEquals(100_000, $log->base_hpp_idr);
        $this->assertEquals(150_000, $log->final_price_idr);
        $this->assertEquals('DEMAND_SURGE', $log->applied_driver);
    }

    public function test_81_1_tick_idempotency_and_minute_aggregates(): void
    {
        $now = Carbon::parse('2026-10-18 10:15:30');

        // 1. Record tick idempotently
        $tick1 = $this->pricingService->recordPriceTick(
            sku: 'TIRE-SPORT-18',
            tickTime: $now,
            priceIdr: 1_250_000,
            demandIndex: 1.45,
            driverSource: 'DEMAND_SURGE'
        );

        $tick2 = $this->pricingService->recordPriceTick(
            sku: 'TIRE-SPORT-18',
            tickTime: $now,
            priceIdr: 1_250_000,
            demandIndex: 1.45,
            driverSource: 'DEMAND_SURGE'
        );

        $this->assertEquals($tick1->id, $tick2->id);
        $this->assertEquals(1, PriceTick::where('sku', 'TIRE-SPORT-18')->count());

        // 2. Second tick in the same minute updates minute aggregate
        $nextTickTime = Carbon::parse('2026-10-18 10:15:45');
        $this->pricingService->recordPriceTick(
            sku: 'TIRE-SPORT-18',
            tickTime: $nextTickTime,
            priceIdr: 1_300_000,
            demandIndex: 1.55,
            driverSource: 'DEMAND_SURGE'
        );

        $agg = PriceTickAggregate::where('sku', 'TIRE-SPORT-18')
            ->where('bucket_minute', '2026-10-18 10:15:00')
            ->first();

        $this->assertNotNull($agg);
        $this->assertEquals(2, $agg->tick_count);
        $this->assertEquals(1_250_000, $agg->open_price_idr);
        $this->assertEquals(1_300_000, $agg->high_price_idr);
        $this->assertEquals(1_250_000, $agg->low_price_idr);
        $this->assertEquals(1_300_000, $agg->close_price_idr);
    }

    public function test_81_4_quote_freeze_and_immutable_conversion_to_order(): void
    {
        // 1. Freeze quote with cryptographic timelock
        $quote = $this->pricingService->freezePriceQuote(
            sku: 'TIRE-SPORT-18',
            effectivePriceIdr: 1_250_000,
            floorPriceIdr: 1_000_000,
            ceilingPriceIdr: 1_600_000,
            lockMinutes: 15
        );

        $this->assertEquals('LOCKED', $quote->status);
        $this->assertEquals(1_250_000, $quote->frozen_price_idr);
        $this->assertNotNull($quote->quote_hash);
        $this->assertTrue($quote->locked_until->isFuture());

        // 2. Convert to order document
        $converted = $this->pricingService->convertFrozenQuoteToOrder(
            quoteCode: $quote->quote_code,
            orderRef: 'ORD-TEST-8101'
        );

        $this->assertEquals('CONVERTED', $converted->status);
        $this->assertEquals('ORD-TEST-8101', $converted->order_ref);
        $this->assertNotNull($converted->converted_at);

        // 3. Converting again is idempotent
        $idempotent = $this->pricingService->convertFrozenQuoteToOrder(
            quoteCode: $quote->quote_code,
            orderRef: 'ORD-TEST-8101'
        );
        $this->assertEquals('CONVERTED', $idempotent->status);
    }

    public function test_81_4_expired_quote_conversion_rejected(): void
    {
        $quote = FrozenQuote::create([
            'quote_code' => 'QT-EXPIRED-TEST',
            'sku' => 'PART-EXPIRED',
            'frozen_price_idr' => 100_000,
            'floor_price_idr' => 80_000,
            'ceiling_price_idr' => 150_000,
            'locked_until' => now()->subMinutes(5),
            'status' => 'LOCKED',
            'quote_hash' => hash('sha256', 'QT-EXPIRED-TEST:PART-EXPIRED:100000:'.now()->subMinutes(5)->toIso8601String()),
        ]);

        $this->expectException(DomainException::class);
        $this->pricingService->convertFrozenQuoteToOrder('QT-EXPIRED-TEST', 'ORD-FAIL-01');
    }

    public function test_81_5_channel_specific_quoting(): void
    {
        $hpp = 100_000;
        $het = 200_000;
        $demand = 1.30;

        $b2c = $this->pricingService->quoteForChannel('STORE_B2C', 'SKU-CHANNEL-01', $hpp, $het, $demand);
        $wholesale = $this->pricingService->quoteForChannel('DISTRIBUTOR_WHOLESALE', 'SKU-CHANNEL-01', $hpp, $het, $demand);
        $vending = $this->pricingService->quoteForChannel('VENDING', 'SKU-CHANNEL-01', $hpp, $het, $demand);

        // Vending should carry premium surge factor compared to wholesale bulk
        $this->assertGreaterThanOrEqual($wholesale['effective_price_idr'], $vending['effective_price_idr']);
        $this->assertGreaterThanOrEqual($hpp, $b2c['effective_price_idr']);
    }

    public function test_81_6_analytics_summary_reports_zero_guardrail_violations(): void
    {
        $now = Carbon::parse('2026-10-18 11:00:00');
        $this->pricingService->recordPriceTick('SKU-ANALYTICS-01', $now, 120_000, 1.2, 'DYNAMIC_ALGO');
        $this->pricingService->recordPriceTick('SKU-ANALYTICS-01', $now->copy()->addSecond(), 125_000, 1.25, 'DEMAND_SURGE');

        $this->pricingService->freezePriceQuote('SKU-ANALYTICS-01', 125_000, 100_000, 180_000, 30);

        $summary = $this->pricingService->getAnalyticsSummary('SKU-ANALYTICS-01');

        $this->assertEquals(2, $summary['total_ticks']);
        $this->assertEquals(120_000, $summary['min_price_idr']);
        $this->assertEquals(125_000, $summary['max_price_idr']);
        $this->assertEquals(0, $summary['guardrail_violations']);
        $this->assertEquals(1, $summary['quotes_locked_count']);
    }

    public function test_81_7_pricing_audit_command_enforces_zero_discrepancy(): void
    {
        $this->artisan('pricing:audit')->assertSuccessful();

        // Corrupt: converted quote without order ref
        $badQuote = FrozenQuote::create([
            'quote_code' => 'QT-BAD-01',
            'sku' => 'SKU-BAD',
            'frozen_price_idr' => 100_000,
            'floor_price_idr' => 80_000,
            'ceiling_price_idr' => 150_000,
            'quote_hash' => 'dummy',
            'status' => 'CONVERTED',
            'order_ref' => null, // violation
            'locked_until' => now()->addMinutes(30),
        ]);

        $this->artisan('pricing:audit')
            ->assertFailed()
            ->expectsOutputToContain('quote.converted_no_order_ref');

        // Cleanup bad quote and verify clean state
        $badQuote->delete();
        $this->artisan('pricing:audit')->assertSuccessful();
    }

    public function test_81_http_endpoints_and_rbac(): void
    {
        // 1. Auditor/admin can view ticks index
        $response = $this->actingAs($this->admin)->get(route('pricing.ticks', ['sku' => 'PART-BRAKE-01']));
        $response->assertOk();
        $response->assertViewIs('pricing::ticks');

        // 2. Procurement can ingest tick via POST
        $postTick = $this->actingAs($this->procurementUser)->post(route('pricing.ticks.store'), [
            'sku' => 'PART-HTTP-01',
            'price_idr' => 150000,
            'demand_index' => 1.35,
            'driver_source' => 'DEMAND_SURGE',
        ]);
        $postTick->assertRedirect();
        $this->assertDatabaseHas('prc_price_ticks', ['sku' => 'PART-HTTP-01', 'price_idr' => 150000]);

        // 3. Procurement can freeze quote via POST
        $postFreeze = $this->actingAs($this->procurementUser)->post(route('pricing.quote.freeze'), [
            'sku' => 'PART-HTTP-01',
            'base_hpp_idr' => 100000,
            'het_ceiling_idr' => 180000,
            'demand_index' => 1.25,
            'channel' => 'STORE_B2C',
        ]);
        $postFreeze->assertRedirect();
        $frozen = FrozenQuote::where('sku', 'PART-HTTP-01')->first();
        $this->assertNotNull($frozen);

        // 4. Convert quote via POST
        $postConvert = $this->actingAs($this->procurementUser)->post(route('pricing.quote.convert'), [
            'quote_code' => $frozen->quote_code,
            'order_ref' => 'ORD-HTTP-CONVERTED',
        ]);
        $postConvert->assertRedirect();
        $this->assertEquals('CONVERTED', $frozen->fresh()->status);
    }
}
