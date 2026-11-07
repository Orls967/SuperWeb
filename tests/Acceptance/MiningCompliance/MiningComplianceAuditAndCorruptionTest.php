<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningCompliance;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Application\Actions\CalculateVerifiedRoyaltyAction;
use Modules\Mining\Application\Actions\RecordHashChainWeighbridgeTicketAction;
use Modules\Mining\Domain\Models\MiningRoyaltyRateVersion;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Tests\TestCase;

final class MiningComplianceAuditAndCorruptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_royalty_and_compliance_audit_passes_clean_and_fails_on_tampering(): void
    {
        $this->seed(LedgerAccountsSeeder::class);

        $site = MiningSite::create([
            'site_code' => 'SITE-AUD-94',
            'name' => 'Site Audit 94',
            'commodity' => 'NICKEL',
            'location' => 'Morowali',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $stockpile = MiningStockpile::create([
            'site_id' => $site->id,
            'stockpile_code' => 'STK-AUD-94',
            'total_kg' => 0,
            'avg_grade_pct' => 1.7,
        ]);

        MiningRoyaltyRateVersion::create([
            'commodity' => 'NICKEL',
            'product_type' => 'NICKEL_ORE',
            'mining_method' => 'OPEN_PIT',
            'calorific_min_kcal' => 0,
            'calorific_max_kcal' => 99999,
            'price_tier_min_usd_cents' => 0,
            'price_tier_max_usd_cents' => 1799999,
            'rate_bps' => 1400,
            'effective_from' => '2025-04-26',
            'version' => 1,
            'source' => 'PP 19/2025',
            'label' => 'regulasi',
        ]);

        app(RecordHashChainWeighbridgeTicketAction::class)->execute([
            'site_id' => (string) $site->id,
            'stockpile_id' => (string) $stockpile->id,
            'vehicle_plate' => 'DT-AUD-01',
            'gross_weight_kg' => 10050000,
            'tare_weight_kg' => 50000,
            'weighed_at' => '2026-11-05 11:00:00',
            'destination' => 'STOCKPILE_IN',
        ]);

        app(CalculateVerifiedRoyaltyAction::class)->execute([
            'site_id' => (string) $site->id,
            'period_month' => '2026-11',
            'commodity' => 'NICKEL',
            'product_type' => 'NICKEL_ORE',
            'mining_method' => 'OPEN_PIT',
            'calorific_kcal' => 0,
            'reference_price_usd_cents' => 1520000,
            'benchmark_invoice_price_usd_cents' => 3825,
            'exchange_rate_idr' => 16250,
            'production_date' => '2026-11-05',
            'idempotency_key' => 'min:royalty-calc:SITE-AUD-94:2026-11',
        ]);

        $this->artisan('mining:check-royalty')->assertExitCode(0);

        // Tamper stockpile total_kg directly in DB
        DB::table('min_stockpiles')->where('id', $stockpile->id)->update(['total_kg' => 999999]);

        $this->artisan('mining:check-royalty')->assertExitCode(1);
    }
}
