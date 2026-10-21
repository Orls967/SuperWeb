<?php

declare(strict_types=1);

namespace Modules\Rwa\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Rwa\Application\Services\RwaCrossLineDividendDistributionService;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaCrossLineAssetMapping;
use Modules\Rwa\Domain\Models\RwaHolding;
use Tests\TestCase;

class RwaCrossLineDividendDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected RwaCrossLineDividendDistributionService $dividendService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->dividendService = app(RwaCrossLineDividendDistributionService::class);
    }

    public function test_rwa_cross_line_asset_mapping_creation(): void
    {
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-HTL-BALI',
            'name' => 'Grand Bali Resort Luxury Villa Fraction',
            'underlying_asset_type' => 'hotel_units',
            'underlying_asset_id' => 'HTL-BALI-01',
            'appraisal_value_idr' => 20_000_000_000,
            'total_supply_tokens' => 10_000,
            'token_price_idr' => 2_000_000,
            'status' => 'active',
        ]);

        $mapping = RwaCrossLineAssetMapping::create([
            'rwa_asset_id' => $asset->id,
            'source_domain' => 'HOTEL',
            'source_entity_id' => 'HTL-BALI-01',
            'asset_type_code' => 'TIMESHARE_VILLA',
            'annual_yield_target_bps' => 950,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('rwa_cross_line_asset_mappings', [
            'rwa_asset_id' => $asset->id,
            'source_domain' => 'HOTEL',
            'source_entity_id' => 'HTL-BALI-01',
            'annual_yield_target_bps' => 950,
        ]);
        $this->assertEquals($asset->id, $mapping->asset->id);
    }

    public function test_rwa_pro_rata_dividend_distribution_with_dust_reserve_invariant(): void
    {
        // 1. Setup asset with 10,000 units
        $asset = RwaAsset::create([
            'token_symbol' => 'RWA-MIN-TRK',
            'name' => 'Heavy Mining Hauler Unit Token',
            'underlying_asset_type' => 'min_heavy_equipment',
            'underlying_asset_id' => 'MIN-HAULER-99',
            'appraisal_value_idr' => 10_000_000_000,
            'total_supply_tokens' => 10_000,
            'token_price_idr' => 1_000_000,
            'status' => 'active',
        ]);

        // 2. Setup 3 holders with unequal distribution (prime numbers to force dust)
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $user1->id,
            'token_balance' => 3333,
            'locked_balance' => 0,
        ]);

        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $user2->id,
            'token_balance' => 3333,
            'locked_balance' => 0,
        ]);

        RwaHolding::create([
            'rwa_asset_id' => $asset->id,
            'user_id' => $user3->id,
            'token_balance' => 3334,
            'locked_balance' => 0,
        ]);

        // 3. Distribute net operational revenue: Rp 10.000.007 IDR (forces dust remainder)
        $netRevenue = 10_000_007;
        $result = $this->dividendService->distributeOperationalDividends(
            $asset->id,
            $netRevenue,
            'BATCH-DIV-2026-Q4'
        );

        $this->assertEquals('RWA-MIN-TRK', $result['asset_code']);
        $this->assertEquals(3, $result['recipients_count']);

        // Invariant check: distributed + dust === netRevenue
        $this->assertSame(
            $netRevenue,
            $result['distributed_amount_idr'] + $result['dust_reserved_idr'],
            'Mathematical dust invariant must hold: sum(payouts) + dust === total_revenue'
        );

        // 4. Verify Ledger Transaction
        $tx = LedgerTransaction::where('reference_type', 'rwa_assets')
            ->where('reference_id', (string) $asset->id)
            ->firstOrFail();

        $this->assertEquals('rwa_dividend', $tx->type);
        $entries = $tx->entries;
        $this->assertCount(5, $entries); // Source debit, 3 holder credits, 1 dust credit

        // Zero-sum balance check across all entries
        $sum = 0;
        foreach ($entries as $e) {
            $sum += (int) $e->amount->toInt();
        }
        $this->assertSame(0, $sum, 'Double-entry transaction entries must sum to exactly zero');
    }
}
