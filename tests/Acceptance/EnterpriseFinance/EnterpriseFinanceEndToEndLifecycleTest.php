<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\EnterpriseFinance\Application\Services\ConglomerateCapitalAndGovernanceService;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Modules\EnterpriseFinance\Domain\Algorithms\BudgetVarianceAnalyzer;
use Modules\EnterpriseFinance\Domain\Algorithms\ConsolidatedTrialBalanceAggregator;
use Modules\EnterpriseFinance\Domain\Algorithms\SyndicatedLoanCovenantCalculator;
use Modules\EnterpriseFinance\Domain\DTOs\AccountBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\EntityTrialBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantInputDTO;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;
use Tests\TestCase;

class EnterpriseFinanceEndToEndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private EnterpriseFinanceService $service;

    private ConglomerateCapitalAndGovernanceService $governanceService;

    private EnterpriseBudgetCycleStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(EnterpriseFinanceService::class);
        $this->governanceService = app(ConglomerateCapitalAndGovernanceService::class);
        $this->stateMachine = app(EnterpriseBudgetCycleStateMachine::class);
    }

    public function test_full_enterprise_finance_lifecycle_end_to_end(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create(['role' => 'treasury']);

        // 1. Perumusan Anggaran Awal (DRAFT)
        $budget = EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-ENG',
            'fiscal_year' => '2026',
            'cost_center_code' => 'ENGINEERING',
            'account_code' => '5201-TECH',
            'allocated_amount_idr' => 1_000_000_000,
            'encumbered_amount_idr' => 0,
            'spent_amount_idr' => 0,
            'control_type' => 'HARD_STOP',
            'status' => 'DRAFT',
            'created_by' => $creator->id,
        ]);

        // 2. Siklus State Machine: DRAFT -> SUBMITTED -> REVIEWED -> APPROVED -> ACTIVE
        $this->stateMachine->transition($budget, 'SUBMITTED', $creator->id);
        $this->assertSame('SUBMITTED', $budget->status);

        $this->stateMachine->transition($budget, 'REVIEWED', $creator->id);
        $this->assertSame('REVIEWED', $budget->status);

        $this->stateMachine->transition($budget, 'APPROVED', $approver->id);
        $this->assertSame('APPROVED', $budget->status);

        $this->stateMachine->transition($budget, 'ACTIVE', $approver->id);
        $this->assertSame('ACTIVE', $budget->status);

        // 3. Encumbrance Reservasi Komitmen
        $this->service->encumberBudget($budget, 300_000_000);
        $budget->refresh();
        $this->assertSame(300_000_000, $budget->encumbered_amount_idr);

        // 4. Realisasi Belanja Aktual
        $this->service->realizeSpend($budget, 250_000_000);
        $budget->refresh();
        $this->assertSame(50_000_000, $budget->encumbered_amount_idr);
        $this->assertSame(250_000_000, $budget->spent_amount_idr);

        // 5. Analisis Varians Anggaran via Algoritma D9
        $analyzer = new BudgetVarianceAnalyzer;
        $varResult = $analyzer->analyze(new BudgetVarianceInputDTO(
            departmentCode: 'ENGINEERING',
            fiscalYear: '2026',
            allocatedBudgetMinor: $budget->allocated_amount_idr,
            actualSpentMinor: $budget->spent_amount_idr,
            encumberedMinor: $budget->encumbered_amount_idr
        ));

        $this->assertSame(300, $varResult->utilizationPermille); // (250M + 50M) / 1000M = 30%
        $this->assertSame('FAVORABLE', $varResult->classification);
        $this->assertFalse($varResult->isBreached);

        // 6. Konsolidasi Trial Balance Multi-Entitas via Algoritma D8
        $aggregator = new ConsolidatedTrialBalanceAggregator;
        $tbResult = $aggregator->aggregate(new ConsolidatedTrialBalanceInputDTO(
            period: '2026-10',
            reportingCurrency: 'IDR',
            entities: [
                new EntityTrialBalanceDTO('HOLDING', 'PT Nusantara Induk', 'IDR', [
                    new AccountBalanceDTO('1101-CASH', 'Kas Holding', 600_000_000, 0),
                    new AccountBalanceDTO('3101-CAPITAL', 'Modal Holding', 0, 600_000_000),
                ]),
                new EntityTrialBalanceDTO('SUB_TECH', 'PT Solusi Awan', 'IDR', [
                    new AccountBalanceDTO('1201-AR', 'Piutang Tech', 400_000_000, 0),
                    new AccountBalanceDTO('2201-AP', 'Utang Tech', 0, 400_000_000),
                ]),
            ]
        ), strict: true);

        $this->assertTrue($tbResult->isZeroSumBalanced);
        $this->assertSame(1_000_000_000, $tbResult->totalDebitMinor);
        $this->assertSame(1_000_000_000, $tbResult->totalCreditMinor);

        // 7. Evaluasi Kepatuhan Covenant Utang Sindikasi via Algoritma D7
        $covCalc = new SyndicatedLoanCovenantCalculator;
        $covResult = $covCalc->calculate(new SyndicatedLoanCovenantInputDTO(
            facilityCode: 'FAC-SYND-2026',
            evaluationPeriod: '2026-Q3',
            ebitdaMinor: 600_000_000_000,
            taxPaidMinor: 60_000_000_000,
            debtServiceMinor: 300_000_000_000,
            totalInterestBearingDebtMinor: 1_500_000_000_000,
            totalEquityMinor: 1_000_000_000_000,
            ebitMinor: 500_000_000_000,
            interestExpenseMinor: 150_000_000_000,
            currentAssetsMinor: 800_000_000_000,
            currentLiabilitiesMinor: 500_000_000_000
        ));

        $this->assertTrue($covResult->isFullyCompliant);
        $this->assertSame(1800, $covResult->dscrPermille);
        $this->assertSame(1500, $covResult->derPermille);
    }
}
