<?php

declare(strict_types=1);

namespace Modules\Finance\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Finance\Application\Services\UniversalCreditEvaluationService;
use Tests\TestCase;

class UniversalCreditEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected UniversalCreditEvaluationService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->creditService = app(UniversalCreditEvaluationService::class);
    }

    public function test_credit_profile_360_scoring_and_tier_classification(): void
    {
        // 1. Excellent profile -> Tier A (Score >= 750)
        // (30*900 + 25*850 + 20*900 + 15*800 + 10*850) / 100 = (27000 + 21250 + 18000 + 12000 + 8500) / 100 = 86750 / 100 = 867
        $tierA = $this->creditService->evaluateCreditProfile360(
            volumeScore: 900,
            repaymentScore: 850,
            rwaHoldingsScore: 900,
            productionScore: 800,
            workforceScore: 850
        );

        $this->assertEquals(867, $tierA['score']);
        $this->assertEquals('TIER_A', $tierA['tier']);
        $this->assertEquals(650, $tierA['interest_rate_bps']);
        $this->assertEquals(50_000_000_000, $tierA['max_limit_idr']);
        $this->assertEquals('APPROVED_AUTOMATIC', $tierA['status']);

        // 2. Medium profile -> Tier B (Score 600 - 749)
        // (30*650 + 25*650 + 20*600 + 15*600 + 10*600) / 100 = (19500 + 16250 + 12000 + 9000 + 6000) / 100 = 62750 / 100 = 627
        $tierB = $this->creditService->evaluateCreditProfile360(
            volumeScore: 650,
            repaymentScore: 650,
            rwaHoldingsScore: 600,
            productionScore: 600,
            workforceScore: 600
        );

        $this->assertEquals(627, $tierB['score']);
        $this->assertEquals('TIER_B', $tierB['tier']);
        $this->assertEquals(850, $tierB['interest_rate_bps']);
        $this->assertEquals(10_000_000_000, $tierB['max_limit_idr']);

        // 3. Low profile -> Tier D (Score < 450) -> Rejected
        $tierD = $this->creditService->evaluateCreditProfile360(
            volumeScore: 300,
            repaymentScore: 250,
            rwaHoldingsScore: 200,
            productionScore: 300,
            workforceScore: 200
        );

        $this->assertEquals('TIER_D', $tierD['tier']);
        $this->assertEquals('REJECTED_INSUFFICIENT_SCORE', $tierD['status']);
    }

    public function test_credit_facility_disbursement_posts_balanced_double_entry(): void
    {
        $disbursed = $this->creditService->disburseCreditFacility(
            loanId: 'LN-MINING-HAULER-001',
            borrowerId: 'BORROWER-PT-MINING-MAJU',
            principalAmountIdr: 5_000_000_000,
            purposeCode: 'HEAVY_EQUIPMENT_EXPANSION'
        );

        $this->assertEquals('DISBURSED', $disbursed['status']);
        $this->assertEquals(5_000_000_000, $disbursed['disbursed_idr']);

        // Verify ledger transaction
        $tx = LedgerTransaction::where('type', 'loan_disbursement')->firstOrFail();
        $this->assertCount(2, $tx->entries);

        $sum = 0;
        foreach ($tx->entries as $entry) {
            $sum += (int) (string) $entry->amount;
        }
        $this->assertSame(0, $sum, 'Loan disbursement ledger entries must balance to exactly zero');
    }
}
