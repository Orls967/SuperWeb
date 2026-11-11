<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\tests\Unit\Algorithms;

use Modules\EnterpriseFinance\Domain\Algorithms\Consolidation\ConsolidationEliminationEngine;
use Modules\EnterpriseFinance\Domain\Algorithms\Encumbrance\BudgetVarianceAnalyzer;
use Modules\EnterpriseFinance\Domain\Algorithms\Forecasting\CorporateCashRunwayForecaster;
use Modules\EnterpriseFinance\Domain\Algorithms\Goodwill\MergerAcquisitionValuationEngine;
use Modules\EnterpriseFinance\Domain\Algorithms\Reconciliation\SubledgerReconciler;
use Modules\EnterpriseFinance\Domain\Algorithms\Tax\ProgressiveTaxCalculator;
use Modules\EnterpriseFinance\Domain\Algorithms\Valuation\CorporateSyndicationCalculator;
use Modules\EnterpriseFinance\Domain\Algorithms\Valuation\SyndicatedFacilityCovenantMonitor;
use Tests\TestCase;

class EnterpriseFinanceAlgorithmsComprehensiveTest extends TestCase
{
    /**
     * Test 1: Progressive Tax Calculator (Article 17 and TER lookup).
     */
    public function test_progressive_tax_calculator_brackets_and_ter(): void
    {
        // 1. PTKP kalkulasi
        $ptkpTk0 = ProgressiveTaxCalculator::calculatePtkpAnnual('TK/0');
        $this->assertSame(54_000_000_00, $ptkpTk0);

        $ptkpK3 = ProgressiveTaxCalculator::calculatePtkpAnnual('K/3');
        $this->assertSame(72_000_000_00, $ptkpK3);

        // 2. Pasal 17 Pajak Progresif (PKP Rp 100.000.000)
        // 60 jt * 5% = 3 jt; 40 jt * 15% = 6 jt; Total = 9 jt
        $tax100m = ProgressiveTaxCalculator::calculateArticle17AnnualTax(100_000_000_00);
        $this->assertSame(9_000_000_00, $tax100m['tax_total_minor']);
        $this->assertSame(3_000_000_00, $tax100m['bracket_1_minor']);
        $this->assertSame(6_000_000_00, $tax100m['bracket_2_minor']);

        // 3. TER Bulanan Kategori A
        $rateA = ProgressiveTaxCalculator::lookupTerMonthlyRateBps('A', 10_000_000_00);
        $this->assertSame(200, $rateA); // 2.00%
    }

    /**
     * Test 2: Subledger Reconciler and Gapless Sequence Verification.
     */
    public function test_subledger_reconciler_and_gapless_sequence(): void
    {
        // 1. Ledger Balance check
        $balancedEntries = [
            ['debit_minor' => 1000, 'credit_minor' => 0, 'account' => 'A'],
            ['debit_minor' => 0, 'credit_minor' => 1000, 'account' => 'B'],
        ];
        $res = SubledgerReconciler::verifyLedgerBalance($balancedEntries);
        $this->assertTrue($res['is_balanced']);
        $this->assertSame(0, $res['net_discrepancy_minor']);

        // 2. Domain Reconciliation
        $recon = SubledgerReconciler::reconcileDomain(500000, 500000, 'AR');
        $this->assertTrue($recon['is_reconciled']);
        $this->assertSame('PERFECT_MATCH', $recon['status']);

        // 3. Gapless Verification
        $seq = ['010.001-26.00000001', '010.001-26.00000002', '010.001-26.00000003'];
        $gapless = SubledgerReconciler::verifyGaplessSequence($seq, '010.001-26.');
        $this->assertTrue($gapless['is_gapless']);
        $this->assertEmpty($gapless['missing_sequences']);
    }

    /**
     * Test 3: Budget Variance Analyzer (Utilization and Reallocation Feasibility).
     */
    public function test_budget_variance_analyzer(): void
    {
        $util = BudgetVarianceAnalyzer::analyzeUtilization(
            allocatedMinor: 1000_00,
            encumberedMinor: 200_00,
            spentMinor: 600_00
        );
        $this->assertSame(8000, $util['utilization_bps']); // 80%
        $this->assertSame(200_00, $util['available_minor']);
        $this->assertSame('WARNING_THRESHOLD', $util['status']);

        // Reallocation
        $feas = BudgetVarianceAnalyzer::evaluateReallocationFeasibility(
            donorAllocatedMinor: 1000_00,
            donorCommittedMinor: 400_00,
            transferAmountMinor: 300_00
        );
        $this->assertTrue($feas['is_transfer_feasible']);
        $this->assertSame(300_00, $feas['donor_available_after_minor']);
    }

    /**
     * Test 4: Syndicated Loan and Covenants Calculator.
     */
    public function test_syndicated_loan_and_covenants(): void
    {
        $sched = CorporateSyndicationCalculator::generateAmortizationSchedule(
            principalMinor: 12_000_000_00,
            annualInterestRateBps: 1200, // 12%
            tenorMonths: 12,
            gracePeriodMonths: 0
        );
        $this->assertCount(12, $sched);
        $this->assertSame(0, $sched[11]['ending_balance_minor']);

        // Covenant check
        $cov = CorporateSyndicationCalculator::evaluateCovenants(
            ebitdaMinor: 5_000_000_00,
            ebitMinor: 4_000_000_00,
            interestExpenseMinor: 1_000_000_00,
            principalDueMinor: 1_000_000_00,
            taxPaidMinor: 500_000_00,
            totalDebtMinor: 8_000_000_00,
            totalEquityMinor: 10_000_000_00,
            currentAssetsMinor: 15_000_000_00,
            currentLiabilitiesMinor: 10_000_000_00
        );
        $this->assertTrue($cov['is_compliant']);
    }

    /**
     * Test 5: M&A Purchase Price Allocation and Consolidation Eliminations.
     */
    public function test_ma_ppa_and_consolidation_eliminations(): void
    {
        $ppa = MergerAcquisitionValuationEngine::executePurchasePriceAllocation(
            purchaseConsiderationMinor: 50_000_000_00,
            bookValueNetAssetsMinor: 30_000_000_00,
            tangibleFairValueAdjustmentMinor: 5_000_000_00,
            identifiableIntangiblesMinor: 8_000_000_00,
            deferredTaxLiabilityMinor: 2_000_000_00
        );
        // Net assets = 30 + 5 + 8 - 2 = 41 jt. Goodwill = 50 - 41 = 9 jt.
        $this->assertSame(9_000_000_00, $ppa['goodwill_recognized_minor']);
        $this->assertFalse($ppa['is_bargain_purchase']);

        // Consolidation Intercompany Elimination
        $elim = ConsolidationEliminationEngine::eliminateIntercompanyInventoryProfit(
            intercompanySalesMinor: 10_000_000_00,
            endingInventoryMinor: 2_000_000_00,
            sellerGrossMarginBps: 2000 // 20%
        );
        $this->assertSame(400_000_00, $elim['unrealized_inventory_profit_minor']);
        $this->assertSame(1_600_000_00, $elim['adjusted_inventory_minor']);
    }

    /**
     * Test 6: Cash Runway Forecaster and Sensitivity Shock.
     */
    public function test_cash_runway_forecaster_and_shock(): void
    {
        $runway = CorporateCashRunwayForecaster::simulateRunwayStressScenarios(
            currentCashMinor: 60_000_000_00,
            monthlyBaselineOpexMinor: 10_000_000_00,
            monthlyBaselineRevenueMinor: 5_000_000_00
        );
        // Net burn base = 10 - 5 = 5 jt. Runway = 60 / 5 = 12 months.
        $this->assertSame(12, $runway['base_case_runway_months']);

        $shock = SyndicatedFacilityCovenantMonitor::stressTestInterestRateHikes(
            principalOutstandingMinor: 100_000_000_00,
            baselineAnnualRateBps: 800, // 8%
            ebitdaMinor: 40_000_000_00,
            ebitMinor: 30_000_000_00,
            annualPrincipalDueMinor: 10_000_000_00
        );
        $this->assertFalse($shock['is_vulnerable_to_rate_hike']);
    }
}
