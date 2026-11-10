<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Algorithms\ConsolidatedTrialBalanceAggregator;
use Modules\EnterpriseFinance\Domain\Algorithms\SyndicatedLoanCovenantCalculator;
use Modules\EnterpriseFinance\Domain\DTOs\AccountBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedFinancialRatiosDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\EntityTrialBalanceDTO;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantInputDTO;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationEntry;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationRun;
use Modules\EnterpriseFinance\Domain\Models\DebtCovenantEvaluation;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SodRule;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
use Modules\EnterpriseFinance\Domain\Models\WorkingCapitalMetric;
use Modules\EnterpriseFinance\Domain\Services\ConsolidatedFinancialRatioAnalyzer;

class EnterpriseFinanceService
{
    /**
     * 54.1 Enterprise Budgeting & Encumbrance Hard-Stop
     */
    public function allocateBudget(
        string $fiscalYear,
        string $costCenter,
        string $accountCode,
        int $allocatedIdr,
        string $controlType = 'HARD_STOP'
    ): EnterpriseBudget {
        return EnterpriseBudget::updateOrCreate(
            [
                'fiscal_year' => $fiscalYear,
                'cost_center_code' => $costCenter,
                'account_code' => $accountCode,
            ],
            [
                'budget_code' => 'BGT-'.$fiscalYear.'-'.strtoupper($costCenter),
                'allocated_amount_idr' => $allocatedIdr,
                'control_type' => $controlType,
            ]
        );
    }

    public function encumberBudget(EnterpriseBudget $budget, int $amountIdr): EnterpriseBudget
    {
        $available = $budget->allocated_amount_idr - ($budget->encumbered_amount_idr + $budget->spent_amount_idr);

        if ($amountIdr > $available && $budget->control_type === 'HARD_STOP') {
            throw new InvalidArgumentException("Transaksi melebihi sisa pagu anggaran ({$budget->budget_code}). Hard-stop aktif.");
        }

        $budget->increment('encumbered_amount_idr', $amountIdr);

        return $budget;
    }

    public function realizeSpend(EnterpriseBudget $budget, int $amountIdr): EnterpriseBudget
    {
        $budget->update([
            'encumbered_amount_idr' => max(0, $budget->encumbered_amount_idr - $amountIdr),
            'spent_amount_idr' => $budget->spent_amount_idr + $amountIdr,
        ]);

        return $budget;
    }

    /**
     * 54.3 Simulator Kepatuhan Pajak (PPN & PPh Rekonsiliasi)
     */
    public function recordTaxSummary(
        string $period,
        string $taxType,
        int $taxBaseIdr,
        int $inputTaxIdr = 0,
        int $outputTaxIdr = 0,
        int $withheldTaxIdr = 0
    ): EnterpriseTaxSummary {
        $payable = ($taxType === 'PPN') ? max(0, $outputTaxIdr - $inputTaxIdr) : $withheldTaxIdr;

        return EnterpriseTaxSummary::updateOrCreate(
            [
                'period' => $period,
                'tax_type' => strtoupper($taxType),
            ],
            [
                'tax_base_idr' => $taxBaseIdr,
                'input_tax_idr' => $inputTaxIdr,
                'output_tax_idr' => $outputTaxIdr,
                'withheld_tax_idr' => $withheldTaxIdr,
                'payable_or_refundable_idr' => $payable,
                'status' => 'draft',
            ]
        );
    }

    /**
     * 54.4 Segregation of Duties (SoD) Validation Engine
     */
    public function registerSodRule(
        string $roleA,
        string $roleB,
        string $desc,
        string $risk = 'CRITICAL'
    ): SodRule {
        return SodRule::updateOrCreate(
            [
                'role_a' => $roleA,
                'role_b' => $roleB,
            ],
            [
                'rule_code' => 'SOD-'.strtoupper(Str::random(6)),
                'description' => $desc,
                'risk_level' => strtoupper($risk),
                'is_active' => true,
            ]
        );
    }

    public function checkSodConflict(array $userRoles): array
    {
        $activeRules = SodRule::where('is_active', true)->get();
        $conflicts = [];

        foreach ($activeRules as $rule) {
            if (in_array($rule->role_a, $userRoles, true) && in_array($rule->role_b, $userRoles, true)) {
                $conflicts[] = [
                    'rule_code' => $rule->rule_code,
                    'roles' => [$rule->role_a, $rule->role_b],
                    'risk' => $rule->risk_level,
                    'description' => $rule->description,
                ];
            }
        }

        return [
            'has_conflict' => (count($conflicts) > 0),
            'conflict_count' => count($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * 54.6 Kalender Kepatuhan Regulasi
     */
    public function scheduleComplianceDeadline(
        string $title,
        string $authority,
        string $dueDate,
        string $assignedRole = 'auditor'
    ): ComplianceDeadline {
        return ComplianceDeadline::create([
            'item_code' => 'REG-'.strtoupper(Str::random(6)),
            'title' => $title,
            'regulatory_body' => strtoupper($authority),
            'due_date' => $dueDate,
            'assigned_role' => $assignedRole,
            'status' => 'pending',
        ]);
    }

    /**
     * 54.2 Konsolidasi Trial Balance Multi-Entitas via Algoritma D8.
     *
     * @param  list<array{entity_code: string, entity_name: string, currency: string, accounts: list<array{account_code: string, account_name: string, debit: int, credit: int}>}>  $entitiesData
     */
    public function consolidateTrialBalance(
        string $period,
        array $entitiesData,
        string $reportingCurrency = 'IDR',
        ?int $userId = null
    ): ConsolidationRun {
        $entitiesDTO = [];
        foreach ($entitiesData as $eData) {
            $accountsDTO = [];
            foreach ($eData['accounts'] as $acc) {
                $accountsDTO[] = new AccountBalanceDTO(
                    accountCode: $acc['account_code'],
                    accountName: $acc['account_name'],
                    debitMinor: (int) $acc['debit'],
                    creditMinor: (int) $acc['credit']
                );
            }

            $entitiesDTO[] = new EntityTrialBalanceDTO(
                entityCode: $eData['entity_code'],
                entityName: $eData['entity_name'],
                currency: $eData['currency'] ?? 'IDR',
                accounts: $accountsDTO
            );
        }

        $inputDTO = new ConsolidatedTrialBalanceInputDTO(
            period: $period,
            reportingCurrency: $reportingCurrency,
            entities: $entitiesDTO
        );

        $aggregator = new ConsolidatedTrialBalanceAggregator;
        $resultDTO = $aggregator->aggregate($inputDTO, strict: true);

        return DB::transaction(function () use ($resultDTO, $userId) {
            $run = ConsolidationRun::create([
                'run_code' => 'RUN-TB-'.$resultDTO->period.'-'.strtoupper(Str::random(4)),
                'period' => $resultDTO->period,
                'reporting_currency' => $resultDTO->reportingCurrency,
                'total_entities_count' => $resultDTO->totalEntitiesCount,
                'total_debit_minor' => $resultDTO->totalDebitMinor,
                'total_credit_minor' => $resultDTO->totalCreditMinor,
                'elimination_total_minor' => $resultDTO->totalEliminationMinor,
                'is_balanced' => $resultDTO->isZeroSumBalanced,
                'sha256_signature' => $resultDTO->certificate->sha256Signature,
                'status' => 'PUBLISHED',
                'executed_by' => $userId,
            ]);

            foreach ($resultDTO->aggregatedAccounts as $summary) {
                ConsolidationEntry::create([
                    'consolidation_run_id' => $run->id,
                    'entity_code' => 'CONSOLIDATED',
                    'canonical_account_code' => $summary->canonicalAccountCode,
                    'account_name' => $summary->accountName,
                    'local_debit_minor' => $summary->aggregatedDebitMinor,
                    'local_credit_minor' => $summary->aggregatedCreditMinor,
                    'elimination_debit_minor' => 0,
                    'elimination_credit_minor' => 0,
                    'consolidated_net_minor' => $summary->consolidatedNetMinor,
                ]);
            }

            return $run;
        });
    }

    /**
     * 54.5 Hitung dan Simpan Metrik Modal Kerja (CCC, DSO, DIO, DPO).
     */
    public function calculateWorkingCapital(
        string $entityCode,
        string $period,
        int $revenueMinor,
        int $cogsMinor,
        int $arBalanceMinor,
        int $inventoryBalanceMinor,
        int $apBalanceMinor
    ): WorkingCapitalMetric {
        $dailySales = ($revenueMinor > 0) ? (int) intdiv($revenueMinor, 365) : 1;
        $dailyCogs = ($cogsMinor > 0) ? (int) intdiv($cogsMinor, 365) : 1;

        $dso = (int) intdiv($arBalanceMinor, $dailySales);
        $dio = (int) intdiv($inventoryBalanceMinor, $dailyCogs);
        $dpo = (int) intdiv($apBalanceMinor, $dailyCogs);
        $ccc = ($dso + $dio) - $dpo;

        $tier = ($ccc <= 30) ? 'EXCELLENT' : (($ccc <= 60) ? 'NORMAL' : 'NEEDS_IMPROVEMENT');

        return WorkingCapitalMetric::updateOrCreate(
            [
                'entity_code' => $entityCode,
                'period' => $period,
            ],
            [
                'revenue_minor' => $revenueMinor,
                'cogs_minor' => $cogsMinor,
                'ar_balance_minor' => $arBalanceMinor,
                'inventory_balance_minor' => $inventoryBalanceMinor,
                'ap_balance_minor' => $apBalanceMinor,
                'dso_days' => $dso,
                'dio_days' => $dio,
                'dpo_days' => $dpo,
                'ccc_days' => $ccc,
                'efficiency_tier' => $tier,
            ]
        );
    }

    /**
     * 54.7 Evaluasi Rasio Covenant Utang Sindikasi via Algoritma D7.
     */
    public function evaluateDebtCovenants(
        SyndicatedFacility $facility,
        string $evaluationPeriod,
        int $ebitdaMinor,
        int $taxPaidMinor,
        int $debtServiceMinor,
        int $totalDebtMinor,
        int $totalEquityMinor,
        int $ebitMinor,
        int $interestExpenseMinor,
        int $currentAssetsMinor,
        int $currentLiabilitiesMinor,
        ?int $userId = null
    ): DebtCovenantEvaluation {
        $input = new SyndicatedLoanCovenantInputDTO(
            facilityCode: $facility->facility_code,
            evaluationPeriod: $evaluationPeriod,
            ebitdaMinor: $ebitdaMinor,
            taxPaidMinor: $taxPaidMinor,
            debtServiceMinor: $debtServiceMinor,
            totalInterestBearingDebtMinor: $totalDebtMinor,
            totalEquityMinor: $totalEquityMinor,
            ebitMinor: $ebitMinor,
            interestExpenseMinor: $interestExpenseMinor,
            currentAssetsMinor: $currentAssetsMinor,
            currentLiabilitiesMinor: $currentLiabilitiesMinor
        );

        $calc = new SyndicatedLoanCovenantCalculator;
        $res = $calc->calculate($input);

        return DebtCovenantEvaluation::create([
            'facility_id' => $facility->id,
            'evaluation_period' => $evaluationPeriod,
            'dscr_permille' => $res->dscrPermille,
            'der_permille' => $res->derPermille,
            'iscr_permille' => $res->iscrPermille,
            'current_ratio_permille' => $res->currentRatioPermille,
            'is_compliant' => $res->isFullyCompliant,
            'sha256_signature' => $res->certificate->sha256Signature,
            'certificate_payload_json' => json_encode($res->toArray(), JSON_THROW_ON_ERROR),
            'evaluated_by' => $userId,
        ]);
    }

    /**
     * Pembekuan Anggaran Overdue / Mendekati Pagu Kritis.
     */
    public function freezeOverdueBudgets(string $fiscalYear): int
    {
        return EnterpriseBudget::where('fiscal_year', $fiscalYear)
            ->where('status', 'ACTIVE')
            ->whereRaw('(encumbered_amount_idr + spent_amount_idr) >= allocated_amount_idr')
            ->update(['status' => 'FROZEN']);
    }

    /**
     * 54.9 Analisis Rasio Keuangan Agregat Grup.
     */
    public function analyzeGroupFinancialRatios(
        string $period,
        int $revenueMinor,
        int $ebitdaMinor,
        int $ebitMinor,
        int $netProfitMinor,
        int $interestExpenseMinor,
        int $cashMinor,
        int $arMinor,
        int $currentAssetsMinor,
        int $totalAssetsMinor,
        int $currentLiabilitiesMinor,
        int $interestBearingDebtMinor,
        int $totalEquityMinor
    ): ConsolidatedFinancialRatiosDTO {
        $analyzer = new ConsolidatedFinancialRatioAnalyzer;

        return $analyzer->analyzeRatios(
            period: $period,
            revenueMinor: $revenueMinor,
            ebitdaMinor: $ebitdaMinor,
            ebitMinor: $ebitMinor,
            netProfitMinor: $netProfitMinor,
            interestExpenseMinor: $interestExpenseMinor,
            cashMinor: $cashMinor,
            arMinor: $arMinor,
            currentAssetsMinor: $currentAssetsMinor,
            totalAssetsMinor: $totalAssetsMinor,
            currentLiabilitiesMinor: $currentLiabilitiesMinor,
            interestBearingDebtMinor: $interestBearingDebtMinor,
            totalEquityMinor: $totalEquityMinor
        );
    }

    /**
     * 54.9 Audit Finance Grup & Verifikasi Lima Invarian Terpusat.
     */
    public function auditEnterpriseFinance(): array
    {
        $budgets = EnterpriseBudget::count();
        $taxes = EnterpriseTaxSummary::count();
        $sodRules = SodRule::count();
        $deadlines = ComplianceDeadline::count();
        $facilities = SyndicatedFacility::count();
        $consolidationRuns = ConsolidationRun::count();

        // Invarian 1: encumbered + spent <= allocated on hard stop
        $invalidBudgets = EnterpriseBudget::where('control_type', 'HARD_STOP')
            ->whereRaw('(encumbered_amount_idr + spent_amount_idr) > allocated_amount_idr')
            ->count();

        // Invarian 2: PPN payable = output - input if positive
        $invalidTaxes = EnterpriseTaxSummary::where('tax_type', 'PPN')
            ->whereRaw('output_tax_idr > input_tax_idr AND payable_or_refundable_idr != (output_tax_idr - input_tax_idr)')
            ->count();

        // Invarian 3: Konsolidasi Zero-Sum (Total Debit == Total Credit)
        $invalidConsolidations = ConsolidationRun::where('is_balanced', true)
            ->whereRaw('total_debit_minor != total_credit_minor')
            ->count();

        // Invarian 4: Sindikasi drawn <= commitment dan repaid <= drawn
        $invalidFacilities = SyndicatedFacility::whereRaw('drawn_amount_minor > total_commitment_minor OR repaid_amount_minor > drawn_amount_minor')
            ->count();

        // Invarian 5: Tanggal jatuh tempo fasilitas sindikasi tidak boleh lebih awal dari tanggal mulai
        $invalidFacilityDates = SyndicatedFacility::whereRaw('maturity_date < start_date')
            ->count();

        $discrepancyCount = $invalidBudgets + $invalidTaxes + $invalidConsolidations + $invalidFacilities + $invalidFacilityDates;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'budget_count' => $budgets,
            'tax_count' => $taxes,
            'sod_rule_count' => $sodRules,
            'deadline_count' => $deadlines,
            'facility_count' => $facilities,
            'consolidation_count' => $consolidationRuns,
            'invalid_budgets' => $invalidBudgets,
            'invalid_taxes' => $invalidTaxes,
            'invalid_consolidations' => $invalidConsolidations,
            'invalid_facilities' => $invalidFacilities,
        ];
    }
}
