<?php

declare(strict_types=1);

namespace Tests\Acceptance\Intercompany;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Modules\Core\Domain\Models\Role;
use Modules\Intercompany\Application\Services\GroupEntityRegistry;
use Modules\Intercompany\Domain\Algorithms\BilateralNettedSettlementMatrixCalculator;
use Modules\Intercompany\Domain\Algorithms\MultivariateBenchmarkComparator;
use Modules\Intercompany\Domain\Algorithms\NciDividendCascadeCalculator;
use Modules\Intercompany\Domain\Services\ConsolidationAdjustmentJournalGenerator;
use Modules\Intercompany\Domain\Services\IntercompanyAssetValuationDisputeResolver;
use Modules\Intercompany\Domain\Services\IntercompanyCashPoolSweeper;
use Tests\TestCase;

class IntercompanyPhase52CompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['holding_cfo', 'admin', 'auditor', 'treasury', 'legal'] as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['label' => ucwords(str_replace('_', ' ', $roleName)), 'description' => $roleName]
            );
        }
    }

    /**
     * Test 1: GroupEntityRegistry - Hirarki, Kepemilikan Efektif, & Deteksi Siklus Sirkular.
     */
    public function test_group_entity_registry_hierarchy_and_cycle_rejection(): void
    {
        $registry = new GroupEntityRegistry;

        $registry->registerEntity('HOLDING', 'PT Holding Utama', 'ID', 'IDR', true);
        $registry->registerEntity('SUB_A', 'PT Anak Manufaktur A', 'ID', 'IDR', false);
        $registry->registerEntity('SUB_B', 'PT Cucu Ritel B', 'ID', 'IDR', false);
        $registry->registerEntity('ASSOC_C', 'PT Asosiasi Distribusi C', 'ID', 'IDR', false);

        // Holding memiliki 80% SubA (8000 bps)
        $registry->registerOwnershipLink('HOLDING', 'SUB_A', 8000, 'FULL');
        // SubA memiliki 75% SubB (7500 bps)
        $registry->registerOwnershipLink('SUB_A', 'SUB_B', 7500, 'FULL');
        // Holding memiliki 30% AssocC (3000 bps)
        $registry->registerOwnershipLink('HOLDING', 'ASSOC_C', 3000, 'EQUITY');

        // Kepemilikan Efektif Holding ke SubB: 80% * 75% = 60% (6000 bps)
        $effB = $registry->calculateEffectiveOwnership('HOLDING', 'SUB_B');
        $this->assertSame(6000, $effB);
        $this->assertSame('FULL', $registry->determineConsolidationMethod($effB));
        $this->assertSame(4000, $registry->calculateNciShare($effB)); // NCI 40%

        // Kepemilikan Asosiasi: 30% (3000 bps) -> EQUITY
        $effC = $registry->calculateEffectiveOwnership('HOLDING', 'ASSOC_C');
        $this->assertSame(3000, $effC);
        $this->assertSame('EQUITY', $registry->determineConsolidationMethod($effC));

        // Bangun Pohon Konsolidasi
        $tree = $registry->buildConsolidationTree('HOLDING');
        $this->assertSame('HOLDING', $tree['root_entity']);
        $this->assertContains('SUB_A', $tree['perimeter']['full']);
        $this->assertContains('SUB_B', $tree['perimeter']['full']);
        $this->assertContains('ASSOC_C', $tree['perimeter']['equity']);

        // Sabotase Sirkularitas: SubB mencoba memiliki saham di Holding (siklus A -> B -> Holding)
        $this->expectException(InvalidArgumentException::class);
        $registry->registerOwnershipLink('SUB_B', 'HOLDING', 1000, 'COST');
    }

    /**
     * Test 2: MultivariateBenchmarkComparator - PMK 172/2023, IQR, & Penyesuaian True-Up.
     */
    public function test_multivariate_benchmark_comparator_and_true_up(): void
    {
        $comparator = new MultivariateBenchmarkComparator;

        $config = [
            'rule_code' => 'TP-MFG-001',
            'tested_entity_code' => 'ENT_MANUF_01',
            'method' => 'TNMM',
            'period' => '2026',
            'prime_rate_bps' => 750,
        ];

        // Pihak teruji dengan marjin rendah 4.00% (400 bps)
        $testedParty = [
            'revenue_minor' => 1_000_000_000_00,
            'cogs_minor' => 800_000_000_00,
            'opex_minor' => 160_000_000_00,
            'operating_profit_minor' => 40_000_000_00, // 400 bps
            'accounts_receivable_minor' => 120_000_000_00,
            'inventory_minor' => 100_000_000_00,
            'accounts_payable_minor' => 80_000_000_00,
        ];

        // Pembanding independen dengan marjin berkisar 7% - 11%
        $comparators = [
            ['name' => 'Comp A', 'revenue_minor' => 100_000_00, 'cogs_minor' => 70_000_00, 'opex_minor' => 23_000_00, 'operating_profit_minor' => 7_000_00, 'accounts_receivable_minor' => 10_000_00, 'inventory_minor' => 10_000_00, 'accounts_payable_minor' => 8_000_00], // 700 bps
            ['name' => 'Comp B', 'revenue_minor' => 100_000_00, 'cogs_minor' => 70_000_00, 'opex_minor' => 22_000_00, 'operating_profit_minor' => 8_000_00, 'accounts_receivable_minor' => 10_000_00, 'inventory_minor' => 10_000_00, 'accounts_payable_minor' => 8_000_00], // 800 bps
            ['name' => 'Comp C', 'revenue_minor' => 100_000_00, 'cogs_minor' => 70_000_00, 'opex_minor' => 21_000_00, 'operating_profit_minor' => 9_000_00, 'accounts_receivable_minor' => 10_000_00, 'inventory_minor' => 10_000_00, 'accounts_payable_minor' => 8_000_00], // 900 bps
            ['name' => 'Comp D', 'revenue_minor' => 100_000_00, 'cogs_minor' => 70_000_00, 'opex_minor' => 20_000_00, 'operating_profit_minor' => 10_000_00, 'accounts_receivable_minor' => 10_000_00, 'inventory_minor' => 10_000_00, 'accounts_payable_minor' => 8_000_00], // 1000 bps
            ['name' => 'Comp E', 'revenue_minor' => 100_000_00, 'cogs_minor' => 70_000_00, 'opex_minor' => 19_000_00, 'operating_profit_minor' => 11_000_00, 'accounts_receivable_minor' => 10_000_00, 'inventory_minor' => 10_000_00, 'accounts_payable_minor' => 8_000_00], // 1100 bps
        ];

        $res = $comparator->evaluate($config, $testedParty, $comparators);

        $eval = $res->testedPartyEvaluation;
        $this->assertFalse($eval['is_within_range'], 'Marjin 400 bps harus berada di luar rentang IQR');
        $this->assertTrue($eval['requires_true_up']);
        $this->assertSame('UPWARD', $eval['adjustment_direction']);
        $this->assertGreaterThan(0, $eval['recommended_adjustment_minor']);
        $this->assertNotEmpty($res->trueUpJournalVouchers);
    }

    /**
     * Test 3: BilateralNettedSettlementMatrixCalculator - Netting Multilateral & Eliminasi Siklus.
     */
    public function test_multilateral_netting_and_cycle_reduction(): void
    {
        $calculator = new BilateralNettedSettlementMatrixCalculator;

        $config = [
            'session_code' => 'NET-2026-10',
            'settlement_period' => '2026-10',
            'base_currency' => 'IDR',
        ];

        // Siklus utang sirkular: A berhutang ke B (100jt), B berhutang ke C (80jt), C berhutang ke A (50jt)
        $obligations = [
            ['from_entity' => 'ENT_A', 'to_entity' => 'ENT_B', 'amount_minor' => 100_000_000_00, 'currency' => 'IDR'],
            ['from_entity' => 'ENT_B', 'to_entity' => 'ENT_C', 'amount_minor' => 80_000_000_00, 'currency' => 'IDR'],
            ['from_entity' => 'ENT_C', 'to_entity' => 'ENT_A', 'amount_minor' => 50_000_000_00, 'currency' => 'IDR'],
        ];

        $res = $calculator->calculate($config, $obligations);

        // Verifikasi invarian konservasi nol
        $metrics = $res->nettingMetrics;
        $this->assertTrue($metrics['is_zero_sum_balanced']);
        $this->assertGreaterThan(0, $metrics['eliminated_circular_debt_minor']);
        $this->assertLessThan($metrics['total_gross_obligations_minor'], $metrics['total_net_settlement_minor']);
        $this->assertNotEmpty($res->settlementInstructions);
    }

    /**
     * Test 4: NciDividendCascadeCalculator - UU PT Cadangan Wajib & Pembebasan PPh Induk.
     */
    public function test_nci_dividend_cascade_and_tax_exemptions(): void
    {
        $calculator = new NciDividendCascadeCalculator;

        $config = [
            'declaration_code' => 'DIV-2026-SUB',
            'distributing_entity_code' => 'ENT_SUB_01',
            'fiscal_year' => '2026',
            'gross_dividend_minor' => 200_000_000_00,
            'retained_earnings_minor' => 500_000_000_00,
            'issued_capital_minor' => 1_000_000_000_00,
            'existing_statutory_reserve_minor' => 150_000_000_00,
            'parent_entity_code' => 'HOLDING_CORP',
            'parent_ownership_bps' => 7500, // 75.00%
        ];

        $nciShareholders = [
            [
                'name' => 'PT Investor Lokal',
                'type' => 'DOMESTIC_CORPORATE',
                'ownership_bps' => 1500, // 15.00%
            ],
            [
                'name' => 'Global Funds Ltd Singapore',
                'type' => 'FOREIGN_CORPORATE',
                'ownership_bps' => 1000, // 10.00%
                'country_code' => 'SG',
                'has_valid_dgt1_cor' => true,
                'tax_treaty_rate_bps' => 1000, // P3B Singapura 10.00%
            ],
        ];

        $res = $calculator->calculate($config, $nciShareholders);

        $dist = $res->shareholderDistributions;
        // Induk (75%): 0% WHT pembebasan UU HPP
        $this->assertSame(0, $dist[0]['wht_rate_bps']);
        $this->assertTrue($dist[0]['is_eliminated_in_consolidation']);

        // NCI Asing dengan DGT-1: 10% (1000 bps)
        $this->assertSame(1000, $dist[2]['wht_rate_bps']);
        $this->assertSame('P3B_SG_1000BPS', $dist[2]['tax_treaty_applied']);
    }

    /**
     * Test 5: Layanan Lanjutan - PPA Amortisasi, Cash Pool Sweeper, & Valuasi Aset Tetap.
     */
    public function test_advanced_consolidation_services(): void
    {
        // 5.1 PPA Amortization
        $ppaGen = new ConsolidationAdjustmentJournalGenerator;
        $ppaRes = $ppaGen->generatePpaAmortizationJournals(
            ['acquisition_code' => 'ACQ-01', 'fiscal_period' => '2026-10', 'subsidiary_entity_code' => 'SUB_A'],
            [['asset_name' => 'Customer List', 'asset_type' => 'INTANGIBLE', 'initial_fair_value_step_up_minor' => 120_000_000_00, 'useful_life_months' => 60, 'amortized_months_to_date' => 10]]
        );
        $this->assertSame(2_000_000_00, $ppaRes['total_amortization_minor']);
        $this->assertNotEmpty($ppaRes['journals']);

        // 5.2 Cash Pool Sweeper
        $sweeper = new IntercompanyCashPoolSweeper;
        $sweepRes = $sweeper->executeSweep(
            ['pool_code' => 'POOL-1', 'sweep_date' => '2026-10-31', 'header_entity_code' => 'HOLDING'],
            [
                ['entity_code' => 'SUB_A', 'account_number' => 'ACC-A', 'current_balance_minor' => 300_000_000_00, 'minimum_operating_buffer_minor' => 100_000_000_00],
                ['entity_code' => 'SUB_B', 'account_number' => 'ACC-B', 'current_balance_minor' => 50_000_000_00, 'minimum_operating_buffer_minor' => 100_000_000_00],
            ]
        );
        $this->assertSame(200_000_000_00, $sweepRes['total_swept_in_minor']);
        $this->assertSame(50_000_000_00, $sweepRes['total_funded_out_minor']);

        // 5.3 Asset Valuation Dispute Resolver
        $assetResolver = new IntercompanyAssetValuationDisputeResolver;
        $astRes = $assetResolver->evaluateAssetTransfer([
            'transfer_code' => 'TRF-001',
            'asset_tag' => 'AST-MACH-01',
            'source_entity_code' => 'SUB_A',
            'target_entity_code' => 'SUB_B',
            'net_book_value_minor' => 500_000_000_00,
            'accumulated_depreciation_minor' => 200_000_000_00,
            'agreed_transfer_price_minor' => 550_000_000_00,
            'independent_appraisal_value_minor' => 540_000_000_00,
            'remaining_useful_life_months' => 60,
        ]);
        $this->assertSame(50_000_000_00, $astRes['commercial_gain_loss_minor']);
        $this->assertTrue($astRes['is_arms_length_compliant']);
    }

    /**
     * Test 6: Perintah CLI Konsol Baru - TP Audit Pack, Cash Pool Sweep, & Consolidation Lock.
     */
    public function test_new_console_commands_execution(): void
    {
        $exitTp = Artisan::call('group:tp-audit-pack', [
            '--entity' => 'ENT_MANUF_01',
            '--year' => '2026',
            '--rule' => 'TP-MFG-001',
        ]);
        $this->assertSame(0, $exitTp, 'group:tp-audit-pack harus exit 0');

        $exitPool = Artisan::call('group:cash-pool-sweep', [
            '--pool' => 'CPOOL-MAIN',
            '--date' => '2026-10-31',
        ]);
        $this->assertSame(0, $exitPool, 'group:cash-pool-sweep harus exit 0');

        $exitLock = Artisan::call('group:consolidation-lock', [
            '--period' => '2026-10',
            '--holding' => 'HOLDING_CORP',
        ]);
        $this->assertSame(0, $exitLock, 'group:consolidation-lock harus exit 0');
    }
}
