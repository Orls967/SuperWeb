<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Actions;

use InvalidArgumentException;
use Modules\Hcm\Domain\Models\Payroll;

class RunHcmPayrollReconciliationAuditAction
{
    /**
     * @param  string  $period  YYYY-MM
     * @return array{
     *     period: string,
     *     total_employees: int,
     *     total_gross_idr: int,
     *     total_deductions_idr: int,
     *     total_net_idr: int,
     *     total_pph21_idr: int,
     *     total_bpjs_tk_idr: int,
     *     total_bpjs_kes_idr: int,
     *     discrepancies: array<int, string>,
     *     reconciliation_passed: bool
     * }
     */
    public function execute(string $period): array
    {
        if (empty($period)) {
            throw new InvalidArgumentException('Period is required for payroll reconciliation audit.');
        }

        $payrolls = Payroll::where('period', $period)->get();
        if ($payrolls->isEmpty()) {
            throw new InvalidArgumentException("No payroll records found for period {$period}.");
        }

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;
        $totalPph21 = 0;
        $totalBpjsTk = 0;
        $totalBpjsKes = 0;
        $discrepancies = [];

        foreach ($payrolls as $p) {
            $totalGross += $p->gross_salary_idr;
            $totalDeductions += $p->deductions_idr;
            $totalNet += $p->net_salary_idr;
            $totalPph21 += $p->pph21_idr;
            $totalBpjsTk += $p->bpjs_tk_idr;
            $totalBpjsKes += $p->bpjs_kes_idr;

            // Strict audit checks
            // 1. Gross - Deductions == Net
            if (($p->gross_salary_idr - $p->deductions_idr) !== $p->net_salary_idr) {
                $discrepancies[] = "Payroll ID {$p->id}: Gross - Deductions does not match Net salary.";
            }

            // 2. Deductions == PPh21 + BPJS
            if ($p->deductions_idr !== ($p->pph21_idr + $p->bpjs_tk_idr + $p->bpjs_kes_idr)) {
                $discrepancies[] = "Payroll ID {$p->id}: Sum of taxes and BPJS does not match total deductions.";
            }
        }

        // Period-level audit check: total gross - total deductions === total net
        if (($totalGross - $totalDeductions) !== $totalNet) {
            $discrepancies[] = 'Total gross minus total deductions does not equal total net transfer.';
        }

        return [
            'period' => $period,
            'total_employees' => $payrolls->count(),
            'total_gross_idr' => $totalGross,
            'total_deductions_idr' => $totalDeductions,
            'total_net_idr' => $totalNet,
            'total_pph21_idr' => $totalPph21,
            'total_bpjs_tk_idr' => $totalBpjsTk,
            'total_bpjs_kes_idr' => $totalBpjsKes,
            'discrepancies' => $discrepancies,
            'reconciliation_passed' => empty($discrepancies),
        ];
    }
}
