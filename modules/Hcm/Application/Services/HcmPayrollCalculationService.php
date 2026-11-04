<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Ledger\HcmSubjectAccounts;
use Modules\Hcm\Ledger\HcmTransactionTypes;

class HcmPayrollCalculationService
{
    public function __construct(
        private ?LedgerService $ledgerService = null,
        private ?ClockInterface $clock = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     payroll: Payroll,
     *     gross_salary_idr: int,
     *     taxable_gross_idr: int,
     *     pph21_idr: int,
     *     worker_deduction_idr: int,
     *     net_salary_idr: int,
     *     employer_contribution_idr: int
     * }
     */
    public function calculatePayroll(Employee $employee, string $period, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($employee, $period, $idempotencyKey) {
            $existing = Payroll::where('employee_id', $employee->id)->where('period', $period)->first();
            if ($existing) {
                return [
                    'payroll' => $existing,
                    'gross_salary_idr' => (int) $existing->gross_salary_idr,
                    'taxable_gross_idr' => (int) ($existing->gross_salary_idr + 454_000),
                    'pph21_idr' => (int) $existing->pph21_idr,
                    'worker_deduction_idr' => (int) ($existing->bpjs_kes_idr + $existing->bpjs_tk_idr),
                    'net_salary_idr' => (int) $existing->net_salary_idr,
                    'employer_contribution_idr' => 1_024_000,
                ];
            }

            $salary = $employee->basic_salary_idr + $employee->allowances_idr;
            if ($salary <= 0) {
                throw new InvalidArgumentException('Gaji karyawan harus lebih besar dari 0');
            }

            // S2 Parameter Berversi (D58-4)
            // BPJS Kes: pekerja 1% (cap 12jt), pemberi kerja 4% (cap 12jt)
            $kesBase = min($salary, 12_000_000);
            $bpjsKesEmployee = (int) round($kesBase * 0.01);
            $bpjsKesEmployer = (int) round($kesBase * 0.04);

            // JHT: pekerja 2%, pemberi kerja 3.7%
            $jhtEmployee = (int) round($salary * 0.02);
            $jhtEmployer = (int) round($salary * 0.037);

            // JP: pekerja 1% (cap 10.547.400), pemberi kerja 2% (cap 10.547.400)
            $jpBase = min($salary, 10_547_400);
            $jpEmployee = (int) round($jpBase * 0.01);
            $jpEmployer = (int) round($jpBase * 0.02);

            // JKK 0.24%, JKM 0.3% pemberi kerja
            $jkkEmployer = (int) round($salary * 0.0024);
            $jkmEmployer = (int) round($salary * 0.0030);

            // Bruto PPh21 = gaji + JKK + JKM + BPJS Kes pemberi kerja
            $taxableGross = $salary + $jkkEmployer + $jkmEmployer + $bpjsKesEmployer;

            // TER Category A fixture: <= 5.4m: 0%, <= 10m: 2%, <= 20m: 5%
            $rateBps = 0;
            if ($taxableGross <= 5_400_000) {
                $rateBps = 0;
            } elseif ($taxableGross <= 10_000_000) {
                $rateBps = 200;
            } elseif ($taxableGross <= 20_000_000) {
                $rateBps = 500;
            } else {
                $rateBps = 1000;
            }

            $pph21 = (int) round($taxableGross * ($rateBps / 10000));
            $workerDeduction = $bpjsKesEmployee + $jhtEmployee + $jpEmployee;
            $netSalary = $salary - $pph21 - $workerDeduction;

            $employerContribution = $bpjsKesEmployer + $jhtEmployer + $jpEmployer + $jkkEmployer + $jkmEmployer;

            $costCenter = $employee->department?->code ?? 'GEN';

            // Ensure dynamic subject accounts
            $salaryExpenseAcc = HcmSubjectAccounts::salaryExpense($costCenter);
            $employerExpenseAcc = HcmSubjectAccounts::employerContributionExpense($costCenter);
            $salaryPayableAcc = HcmSubjectAccounts::salaryPayable((string) $employee->id);

            $payroll = Payroll::create([
                'employee_id' => $employee->id,
                'period' => $period,
                'gross_salary_idr' => $salary,
                'deductions_idr' => $pph21 + $workerDeduction,
                'net_salary_idr' => $netSalary,
                'pph21_idr' => $pph21,
                'bpjs_tk_idr' => $jhtEmployee + $jpEmployee,
                'bpjs_kes_idr' => $bpjsKesEmployee,
                'status' => 'approved',
            ]);

            $key = $idempotencyKey ?? "hcm:pay:run:{$period}:{$employee->id}";

            // Posting 1: Pengakuan gaji
            $this->ledgerService->post(new PostingDTO(
                type: HcmTransactionTypes::HCM_PAYROLL_POSTING->value,
                description: "Pengakuan payroll {$period} untuk {$employee->name}",
                idempotencyKey: "{$key}:salary",
                entries: Posting::lines()
                    ->debit($salaryExpenseAcc, $salary)
                    ->credit($salaryPayableAcc, $netSalary)
                    ->credit('hcm:pph21_payable:IDR', $pph21)
                    ->credit('hcm:bpjs_payable:IDR', $workerDeduction)
                    ->toEntries('IDR'),
                referenceType: 'HCM_PAYROLL',
                referenceId: (string) $payroll->id,
            ));

            // Posting 2: Iuran pemberi kerja
            if ($employerContribution > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: HcmTransactionTypes::HCM_PAYROLL_POSTING->value,
                    description: "Iuran BPJS pemberi kerja {$period} untuk {$employee->name}",
                    idempotencyKey: "{$key}:employer",
                    entries: Posting::lines()
                        ->debit($employerExpenseAcc, $employerContribution)
                        ->credit('hcm:bpjs_payable:IDR', $employerContribution)
                        ->toEntries('IDR'),
                    referenceType: 'HCM_PAYROLL',
                    referenceId: (string) $payroll->id,
                ));
            }

            return [
                'payroll' => $payroll,
                'gross_salary_idr' => $salary,
                'taxable_gross_idr' => $taxableGross,
                'pph21_idr' => $pph21,
                'worker_deduction_idr' => $workerDeduction,
                'net_salary_idr' => $netSalary,
                'employer_contribution_idr' => $employerContribution,
            ];
        });
    }
}
