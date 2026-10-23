<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Actions;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hcm\Domain\Models\AttendanceLog;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Ledger\HcmTransactionTypes;

class CalculateEnterprisePayrollWithPph21AndBpjsAction
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @param  string  $period  YYYY-MM
     * @return array{
     *     payroll: Payroll,
     *     gross_salary_idr: int,
     *     basic_salary_idr: int,
     *     allowances_idr: int,
     *     overtime_pay_idr: int,
     *     bonus_idr: int,
     *     pph21_idr: int,
     *     bpjs_kes_employee_idr: int,
     *     bpjs_kes_employer_idr: int,
     *     bpjs_tk_employee_idr: int,
     *     bpjs_tk_employer_idr: int,
     *     total_deductions_idr: int,
     *     net_salary_idr: int
     * }
     */
    public function execute(Employee $employee, string $period, int $bonusIdr = 0): array
    {
        if ($employee->basic_salary_idr <= 0) {
            throw new InvalidArgumentException('Employee basic salary must be greater than zero for payroll computation.');
        }

        // 1. Gather overtime from attendance
        $overtimeHours = (float) AttendanceLog::where('employee_id', $employee->id)
            ->where('log_date', 'like', "{$period}%")
            ->sum('overtime_hours');

        $hourlyWage = (int) round($employee->basic_salary_idr / 173.0);
        $overtimePay = (int) round($overtimeHours * $hourlyWage * 1.5);

        $gross = $employee->basic_salary_idr + $employee->allowances_idr + $overtimePay + $bonusIdr;

        // 2. BPJS Kesehatan (Cap at Rp 12,000,000 wage base)
        $bpjsKesWageBase = min($gross, 12000000);
        $bpjsKesEmployer = (int) round($bpjsKesWageBase * 0.04);
        $bpjsKesEmployee = (int) round($bpjsKesWageBase * 0.01);

        // 3. BPJS Ketenagakerjaan
        // JHT: Employer 3.7%, Employee 2.0%
        $jhtEmployee = (int) round($gross * 0.02);
        $jhtEmployer = (int) round($gross * 0.037);

        // JP (Jaminan Pensiun): Cap at Rp 10,042,300 wage base. Employer 2.0%, Employee 1.0%
        $jpWageBase = min($gross, 10042300);
        $jpEmployee = (int) round($jpWageBase * 0.01);
        $jpEmployer = (int) round($jpWageBase * 0.02);

        // JKK (0.24%) and JKM (0.30%) Employer only
        $jkkEmployer = (int) round($gross * 0.0024);
        $jkmEmployer = (int) round($gross * 0.0030);

        $bpjsTkEmployee = $jhtEmployee + $jpEmployee;
        $bpjsTkEmployer = $jhtEmployer + $jpEmployer + $jkkEmployer + $jkmEmployer;

        // 4. PPh 21 TER (Tarif Efektif Rata-rata Category A)
        $pphRate = 0.0;
        if ($gross <= 5400000) {
            $pphRate = 0.0;
        } elseif ($gross <= 10000000) {
            $pphRate = 2.0;
        } elseif ($gross <= 20000000) {
            $pphRate = 5.0;
        } else {
            $pphRate = 10.0;
        }

        $pph21 = (int) round($gross * ($pphRate / 100.0));

        // 5. Deductions & Net Salary
        $totalDeductions = $pph21 + $bpjsKesEmployee + $bpjsTkEmployee;
        $netSalary = $gross - $totalDeductions;

        $payroll = Payroll::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'period' => $period,
            ],
            [
                'gross_salary_idr' => $gross,
                'deductions_idr' => $totalDeductions,
                'net_salary_idr' => $netSalary,
                'pph21_idr' => $pph21,
                'bpjs_tk_idr' => $bpjsTkEmployee,
                'bpjs_kes_idr' => $bpjsKesEmployee,
                'status' => 'approved',
            ]
        );

        // 6. Post double-entry to LedgerService
        if ($gross > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: HcmTransactionTypes::HCM_PAYROLL_POSTING->value,
                description: "Enterprise Payroll for {$employee->name} period {$period}",
                idempotencyKey: "HCM-PAY-ENT-{$payroll->id}-{$period}",
                entries: Posting::lines()
                    ->debit('expense:hcm:salaries:IDR', $gross)
                    ->credit('tax:hcm:pph21_payable:IDR', $pph21)
                    ->credit('ap:hcm:bpjs_payable:IDR', $bpjsKesEmployee + $bpjsTkEmployee)
                    ->credit('ap:hcm:payroll_payable:IDR', $netSalary)
                    ->toEntries('IDR'),
                referenceType: 'HCM_PAYROLL',
                referenceId: (string) $payroll->id,
            ));
        }

        return [
            'payroll' => $payroll,
            'gross_salary_idr' => $gross,
            'basic_salary_idr' => $employee->basic_salary_idr,
            'allowances_idr' => $employee->allowances_idr,
            'overtime_pay_idr' => $overtimePay,
            'bonus_idr' => $bonusIdr,
            'pph21_idr' => $pph21,
            'bpjs_kes_employee_idr' => $bpjsKesEmployee,
            'bpjs_kes_employer_idr' => $bpjsKesEmployer,
            'bpjs_tk_employee_idr' => $bpjsTkEmployee,
            'bpjs_tk_employer_idr' => $bpjsTkEmployer,
            'total_deductions_idr' => $totalDeductions,
            'net_salary_idr' => $netSalary,
        ];
    }
}
