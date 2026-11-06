<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyUtilities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\EscoContract;
use Modules\Egy\Domain\Models\EscoSettlement;
use Modules\Egy\Domain\Models\WaterNetwork;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Tests\TestCase;

final class EgyUtilitiesAuditAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_utility_audit_passes_on_clean_data_and_fails_on_corruption(): void
    {
        $this->seed();
        $ledgerService = app(LedgerService::class);

        // 1. Setup clean district data
        $network = WaterNetwork::create([
            'network_name' => 'DMA-Clean',
            'zone_code' => 'DMA-CLN-01',
            'capacity_liters_per_day' => 100_000,
            'status' => 'operational',
        ]);

        $contract = EscoContract::create([
            'contract_number' => 'ESCO-CLN-001',
            'facility_name' => 'Clean Mall ESCO',
            'client_party_id' => 999,
            'baseline_monthly_kwh' => 100_000,
            'baseline_tariff_idr' => 1400,
            'esco_share_bps' => 7000,
            'status' => 'active',
        ]);

        $settlement = EscoSettlement::create([
            'esco_contract_id' => $contract->id,
            'period_month' => '2026-11',
            'baseline_kwh' => 100_000,
            'cdd_factor_bps' => 10000,
            'adjusted_baseline_kwh' => 100_000,
            'actual_kwh' => 80_000,
            'gross_savings_kwh' => 20_000,
            'rate_idr_per_kwh' => 1400,
            'total_savings_idr' => 28_000_000,
            'esco_share_bps' => 7000,
            'esco_share_idr' => 19_600_000,
            'client_share_idr' => 8_400_000,
            'status' => 'settled',
        ]);

        $clientAr = EgyAccounts::getOrCreateEscoClientAr('999');
        EgyAccounts::ensureAccount('egy:esco_service_revenue:IDR', 'ESCO Service Revenue', AccountKind::REVENUE, 'IDR', false);

        $postingLines = Posting::lines()
            ->debit($clientAr->code, 19_600_000)
            ->credit('egy:esco_service_revenue:IDR', 19_600_000);

        $tx = $ledgerService->post(new PostingDTO(
            type: EgyTransactionTypes::EGY_GRID_BILLING->value,
            description: 'ESCO Clean Settlement',
            idempotencyKey: 'POST-CLEAN-AUDIT-ESCO-999',
            entries: $postingLines->toEntries('IDR'),
            referenceType: 'ESCO_SETTLEMENT',
            referenceId: (string) $settlement->id
        ));

        $settlement->update(['ledger_transaction_id' => (int) $tx->id]);

        // Clean audit check
        $exitClean = Artisan::call('egy:audit');
        $outputClean = Artisan::output();
        $this->assertSame(0, $exitClean, "Audit harus exit 0 pada data bersih: {$outputClean}");

        // 2. Corrupt ledger posting (ubah nilai settlement agar tidak cocok dengan ledger)
        $settlement->update(['esco_share_idr' => 10_000_000]); // selisih dengan ledger (19.600.000 vs 10.000.000)

        $exitCorrupt = Artisan::call('egy:audit');
        $outputCorrupt = Artisan::output();

        $this->assertNotEquals(0, $exitCorrupt, 'Audit harus gagal pada data terkorupsi');
        $this->assertStringContainsString('ESCO Settlement', $outputCorrupt);
    }
}
