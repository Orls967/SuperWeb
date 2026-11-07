<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningCompliance;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Mining\Application\Actions\CalculateVerifiedRoyaltyAction;
use Modules\Mining\Application\Actions\RecordHashChainWeighbridgeTicketAction;
use Modules\Mining\Application\Actions\RunMetallurgicalMassBalanceReconciliationAction;
use Modules\Mining\Domain\Models\MiningRoyaltyRateVersion;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Tests\TestCase;

final class MiningComplianceE2EAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_e2e_weighbridge_ticket_to_mass_balance_m5_and_royalty_m4_ledger_posting(): void
    {
        $this->seed(LedgerAccountsSeeder::class);

        $site = MiningSite::create([
            'site_code' => 'SITE-COAL-94',
            'name' => 'Konsesi Batubara Kaltim 94',
            'commodity' => 'COAL',
            'location' => 'Kutai Timur',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $stockpile = MiningStockpile::create([
            'site_id' => $site->id,
            'stockpile_code' => 'STK-ROM-94',
            'total_kg' => 0,
            'avg_grade_pct' => 50.0,
        ]);

        MiningRoyaltyRateVersion::create([
            'commodity' => 'COAL',
            'product_type' => 'RAW_COAL',
            'mining_method' => 'OPEN_PIT',
            'calorific_min_kcal' => 4201,
            'calorific_max_kcal' => 5200,
            'price_tier_min_usd_cents' => 7000,
            'price_tier_max_usd_cents' => 8999,
            'rate_bps' => 850,
            'effective_from' => '2025-04-26',
            'version' => 1,
            'source' => 'PP 19/2025 Lampiran I',
            'label' => 'regulasi',
        ]);

        // 1. Catat tiket timbang dengan hash-chain (50.000 ton = 50.000.000 kg)
        $ticketAction = app(RecordHashChainWeighbridgeTicketAction::class);
        $ticket = $ticketAction->execute([
            'site_id' => (string) $site->id,
            'stockpile_id' => (string) $stockpile->id,
            'vehicle_plate' => 'KT-9401-HA',
            'gross_weight_kg' => 50070000,
            'tare_weight_kg' => 70000,
            'weighed_at' => '2026-11-05 10:00:00',
            'destination' => 'STOCKPILE_IN',
        ]);

        $this->assertSame(50000000, (int) $ticket->net_weight_kg);
        $this->assertNotEmpty($ticket->current_hash);

        // 2. Rekonsiliasi neraca massa M5 (feed 18.000, konsentrat 14.400, tailing 3.520)
        $reconcileAction = app(RunMetallurgicalMassBalanceReconciliationAction::class);
        $reconcileRun = $reconcileAction->execute([
            'site_id' => (string) $site->id,
            'period_month' => '2026-11',
            'feed_metal_kg' => 18000,
            'concentrate_metal_kg' => 14400,
            'tailings_metal_kg' => 3520,
            'feed_variance_weight' => 4,
            'concentrate_variance_weight' => 1,
            'tailings_variance_weight' => 3,
            'tolerance_kg' => 100,
        ]);

        $this->assertSame(17960, $reconcileRun['reconciled_feed_kg']);
        $this->assertSame(14410, $reconcileRun['reconciled_concentrate_kg']);
        $this->assertSame(3550, $reconcileRun['reconciled_tailings_kg']);
        $this->assertSame(8023, $reconcileRun['recovery_bps']);

        // 3. Hitung royalti M4 dari produksi terverifikasi (50.000 ton @ USD 72,50, HBA 85,00, kurs 16.250)
        $royaltyAction = app(CalculateVerifiedRoyaltyAction::class);
        $result1 = $royaltyAction->execute([
            'site_id' => (string) $site->id,
            'period_month' => '2026-11',
            'commodity' => 'COAL',
            'product_type' => 'RAW_COAL',
            'mining_method' => 'OPEN_PIT',
            'calorific_kcal' => 5000,
            'reference_price_usd_cents' => 8500,
            'benchmark_invoice_price_usd_cents' => 7250,
            'exchange_rate_idr' => 16250,
            'production_date' => '2026-11-05',
            'idempotency_key' => 'min:royalty-calc:SITE-COAL-94:2026-11',
        ]);

        $this->assertSame(850, $result1['rate_bps']);
        $this->assertSame(30812500, $result1['royalty_usd_cents']);
        $this->assertSame(5007031250, $result1['royalty_idr']);

        // Idempotency check: pemanggilan kedua tidak menggandakan jurnal
        $result2 = $royaltyAction->execute([
            'site_id' => (string) $site->id,
            'period_month' => '2026-11',
            'commodity' => 'COAL',
            'product_type' => 'RAW_COAL',
            'mining_method' => 'OPEN_PIT',
            'calorific_kcal' => 5000,
            'reference_price_usd_cents' => 8500,
            'benchmark_invoice_price_usd_cents' => 7250,
            'exchange_rate_idr' => 16250,
            'production_date' => '2026-11-05',
            'idempotency_key' => 'min:royalty-calc:SITE-COAL-94:2026-11',
        ]);

        $this->assertSame($result1['royalty_idr'], $result2['royalty_idr']);

        $payableCredit = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'min:royalty_payable:IDR'))->sum('amount');
        $this->assertSame(5007031250, $payableCredit);
    }
}
