<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SodRule;

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
     * 54.9 Audit Finance Grup
     */
    public function auditEnterpriseFinance(): array
    {
        $budgets = EnterpriseBudget::count();
        $taxes = EnterpriseTaxSummary::count();
        $sodRules = SodRule::count();
        $deadlines = ComplianceDeadline::count();

        // Invariant: encumbered + spent <= allocated on hard stop
        $invalidBudgets = EnterpriseBudget::where('control_type', 'HARD_STOP')
            ->whereRaw('(encumbered_amount_idr + spent_amount_idr) > allocated_amount_idr')
            ->count();

        // Invariant: PPN payable = output - input if positive
        $invalidTaxes = EnterpriseTaxSummary::where('tax_type', 'PPN')
            ->whereRaw('output_tax_idr > input_tax_idr AND payable_or_refundable_idr != (output_tax_idr - input_tax_idr)')
            ->count();

        $discrepancyCount = $invalidBudgets + $invalidTaxes;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'budget_count' => $budgets,
            'tax_count' => $taxes,
            'sod_rule_count' => $sodRules,
            'deadline_count' => $deadlines,
        ];
    }
}
