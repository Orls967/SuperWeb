<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Exceptions\UnbalancedTransactionException;
use Modules\Banking\Support\Posting;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceLedgerPoster;
use Modules\EnterpriseFinance\Domain\Algorithms\BudgetVarianceAnalyzer;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\BudgetVarianceCertificate;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\BudgetVarianceCertificateVerifier;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\ConsolidatedTrialBalanceCertificate;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\ConsolidatedTrialBalanceCertificateVerifier;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\SyndicatedLoanCovenantCertificate;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\SyndicatedLoanCovenantCertificateVerifier;
use Modules\EnterpriseFinance\Domain\Algorithms\ConsolidatedTrialBalanceAggregator;
use Modules\EnterpriseFinance\Domain\Algorithms\SyndicatedLoanCovenantCalculator;
use Modules\EnterpriseFinance\Domain\DTOs\AccountBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\EntityTrialBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantInputDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetInvalidTransitionException;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetMakerCheckerViolationException;
use Modules\EnterpriseFinance\Domain\Exceptions\InvalidCertificateException;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;
use Tests\TestCase;

class EnterpriseFinanceMoneyIntegritySabotageTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledgerService;

    private EnterpriseFinanceLedgerPoster $ledgerPoster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->ledgerService = app(LedgerService::class);
        $this->ledgerPoster = app(EnterpriseFinanceLedgerPoster::class);
    }

    public function test_sabotase_1_corrupted_syndicated_covenant_certificate_is_rejected(): void
    {
        $calculator = new SyndicatedLoanCovenantCalculator;
        $verifier = new SyndicatedLoanCovenantCertificateVerifier;

        $input = new SyndicatedLoanCovenantInputDTO(
            facilityCode: 'FAC-SYND-SABOTAGE',
            evaluationPeriod: '2026-Q3',
            ebitdaMinor: 500_000_000_000,
            taxPaidMinor: 50_000_000_000,
            debtServiceMinor: 250_000_000_000,
            totalInterestBearingDebtMinor: 1_200_000_000_000,
            totalEquityMinor: 800_000_000_000,
            ebitMinor: 420_000_000_000,
            interestExpenseMinor: 140_000_000_000,
            currentAssetsMinor: 650_000_000_000,
            currentLiabilitiesMinor: 450_000_000_000
        );

        $res = $calculator->calculate($input);
        $this->assertTrue($verifier->verify($res->certificate));

        // Sabotase: Modifikasi nilai dscr_permille pada payload sertifikat
        $corruptedPayload = $res->certificate->payload;
        $corruptedPayload['dscr_permille'] = 9999;

        $tamperedCert = new SyndicatedLoanCovenantCertificate(
            payload: $corruptedPayload,
            sha256Signature: $res->certificate->sha256Signature
        );

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($tamperedCert);
    }

    public function test_sabotase_2_corrupted_trial_balance_certificate_is_rejected(): void
    {
        $aggregator = new ConsolidatedTrialBalanceAggregator;
        $verifier = new ConsolidatedTrialBalanceCertificateVerifier;

        $input = new ConsolidatedTrialBalanceInputDTO(
            period: '2026-10',
            reportingCurrency: 'IDR',
            entities: [
                new EntityTrialBalanceDTO('HOLDING', 'PT Induk', 'IDR', [
                    new AccountBalanceDTO('1101-CASH', 'Kas Holding', 500_000_000, 0),
                    new AccountBalanceDTO('3101-CAPITAL', 'Modal Holding', 0, 500_000_000),
                ]),
            ]
        );

        $res = $aggregator->aggregate($input);
        $this->assertTrue($verifier->verify($res->certificate));

        // Sabotase: Ubah total credit untuk merusak zero-sum
        $corruptedPayload = $res->certificate->payload;
        $corruptedPayload['total_credit_minor'] = 499_999_999;

        $tamperedCert = new ConsolidatedTrialBalanceCertificate(
            payload: $corruptedPayload,
            sha256Signature: $res->certificate->sha256Signature
        );

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($tamperedCert);
    }

    public function test_sabotase_3_corrupted_budget_variance_certificate_is_rejected(): void
    {
        $analyzer = new BudgetVarianceAnalyzer;
        $verifier = new BudgetVarianceCertificateVerifier;

        $input = new BudgetVarianceInputDTO(
            departmentCode: 'FINANCE',
            fiscalYear: '2026',
            allocatedBudgetMinor: 1_000_000_000,
            actualSpentMinor: 600_000_000,
            encumberedMinor: 100_000_000
        );

        $res = $analyzer->analyze($input);
        $this->assertTrue($verifier->verify($res->certificate));

        // Sabotase: Balikkan fakta boolean is_breached
        $corruptedPayload = $res->certificate->payload;
        $corruptedPayload['is_breached'] = true;

        $tamperedCert = new BudgetVarianceCertificate(
            payload: $corruptedPayload,
            sha256Signature: $res->certificate->sha256Signature
        );

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($tamperedCert);
    }

    public function test_sabotase_4_unbalanced_ledger_posting_is_strictly_rejected(): void
    {
        // Percobaan posting dengan debit != kredit pada single-sign convention
        $lines = Posting::lines()
            ->debit('ef:cash_disbursement_clearing:IDR', 100_000_000, 'IDR')
            ->credit('ef:syndicated_loan_liability:IDR', 80_000_000, 'IDR'); // Selisih 20 Juta

        $this->expectException(UnbalancedTransactionException::class);

        $this->ledgerService->post(new PostingDTO(
            type: 'SABOTAGE_UNBALANCED_ATTEMPT',
            description: 'Percobaan posting tidak seimbang',
            idempotencyKey: 'SABOTAGE:UNBALANCED:001',
            entries: $lines->toEntries('IDR'),
            referenceType: 'ef_budgets',
            referenceId: '1'
        ));
    }

    public function test_sabotase_5_zero_or_negative_nominal_ledger_posting_is_rejected(): void
    {
        $facility = SyndicatedFacility::create([
            'facility_code' => 'FAC-TEST-SABOTAGE-05',
            'facility_name' => 'Fasilitas Uji Sabotase',
            'facility_type' => 'TERM_LOAN',
            'lead_arranger' => 'Bank Mandiri',
            'currency' => 'IDR',
            'total_commitment_minor' => 1_000_000_000,
            'start_date' => '2026-01-01',
            'maturity_date' => '2028-01-01',
        ]);

        $this->expectException(InvalidArgumentException::class);

        // Sabotase: Nominal penarikan negatif
        $this->ledgerPoster->postSyndicatedDrawdown(
            facility: $facility,
            amountMinor: -50_000_000,
            idempotencyKey: 'SABOTAGE:NEGATIVE:001'
        );
    }

    public function test_sabotase_6_budget_maker_checker_self_approval_is_blocked(): void
    {
        $maker = User::factory()->create(['role' => 'admin']);

        $budget = EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-SELF-APPROVAL',
            'fiscal_year' => '2026',
            'cost_center_code' => 'OPS',
            'account_code' => '5101-EXP',
            'allocated_amount_idr' => 500_000_000,
            'encumbered_amount_idr' => 0,
            'spent_amount_idr' => 0,
            'control_type' => 'HARD_STOP',
            'status' => 'REVIEWED',
            'created_by' => $maker->id,
        ]);

        $sm = app(EnterpriseBudgetCycleStateMachine::class);

        // Sabotase: Pembuat usulan mencoba menyetujui anggarannya sendiri
        $this->expectException(BudgetMakerCheckerViolationException::class);
        $sm->transition($budget, 'APPROVED', operatorId: $maker->id);
    }

    public function test_sabotase_7_invalid_lifecycle_jump_transition_is_blocked(): void
    {
        $maker = User::factory()->create(['role' => 'admin']);
        $checker = User::factory()->create(['role' => 'treasury']);

        $budget = EnterpriseBudget::create([
            'budget_code' => 'BGT-2026-JUMP-STATUS',
            'fiscal_year' => '2026',
            'cost_center_code' => 'HR',
            'account_code' => '5201-EXP',
            'allocated_amount_idr' => 300_000_000,
            'encumbered_amount_idr' => 0,
            'spent_amount_idr' => 0,
            'control_type' => 'HARD_STOP',
            'status' => 'DRAFT',
            'created_by' => $maker->id,
        ]);

        $sm = app(EnterpriseBudgetCycleStateMachine::class);

        // Sabotase: Langsung melompat dari DRAFT ke ACTIVE tanpa SUBMITTED, REVIEWED, APPROVED
        $this->expectException(BudgetInvalidTransitionException::class);
        $sm->transition($budget, 'ACTIVE', operatorId: $checker->id);
    }
}
