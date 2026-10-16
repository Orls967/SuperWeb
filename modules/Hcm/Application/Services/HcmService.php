<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use App\Security\BlindIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Domain\Models\ProductionLaborAllocation;

class HcmService
{
    public function registerEmployee(array $data): Employee
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:160'],
            'basic_salary_idr' => ['required', 'integer', 'min:0'],
            'allowances_idr' => ['required', 'integer', 'min:0'],
            'bank_name' => ['required', 'string', 'max:60'],
            'bank_account_number' => ['required', 'string', 'max:60'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($data) {
            return Employee::create([
                'id' => (string) Str::uuid(),
                'employee_number' => $data['employee_number'] ?? 'EMP-'.strtoupper(Str::random(6)),
                'name' => $data['name'],
                'nik' => $data['nik'] ?? null,
                'nik_bidx' => isset($data['nik']) ? BlindIndex::forNik($data['nik']) : null,
                'nik_hash' => null,
                'email' => $data['email'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'position' => $data['position'] ?? 'Staff',
                'employment_type' => $data['employment_type'] ?? 'PKWTT',
                'basic_salary_idr' => (int) $data['basic_salary_idr'],
                'allowances_idr' => (int) $data['allowances_idr'],
                'bank_name' => (string) $data['bank_name'],
                'bank_account_number' => (string) $data['bank_account_number'],
                'is_active' => true,
            ]);
        });
    }

    public function generatePayroll(Employee $employee, string $period): Payroll
    {
        return DB::transaction(function () use ($employee, $period) {
            $gross = $employee->basic_salary_idr + $employee->allowances_idr;

            // Simulasi BPJS & PPh21 TER (Aritmetika integer half-up)
            $bpjsTk = intdiv($gross * 3 + 50, 100);
            $bpjsKes = intdiv($gross * 1 + 50, 100);
            $pph21 = intdiv($gross * 5 + 50, 100);

            $deductions = $bpjsTk + $bpjsKes + $pph21;
            $net = $gross - $deductions;

            return Payroll::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'period' => $period,
                ],
                [
                    'gross_salary_idr' => $gross,
                    'deductions_idr' => $deductions,
                    'net_salary_idr' => $net,
                    'pph21_idr' => $pph21,
                    'bpjs_tk_idr' => $bpjsTk,
                    'bpjs_kes_idr' => $bpjsKes,
                    'status' => 'approved',
                ]
            );
        });
    }

    public function allocateLaborToProduction(Payroll $payroll, string $workOrderRef, float $hoursWorked, int $hourlyRateIdr): ProductionLaborAllocation
    {
        return DB::transaction(function () use ($payroll, $workOrderRef, $hoursWorked, $hourlyRateIdr) {
            $cost = intdiv((int) round($hoursWorked * 100) * $hourlyRateIdr, 100);

            return ProductionLaborAllocation::create([
                'id' => (string) Str::uuid(),
                'payroll_id' => $payroll->id,
                'work_order_ref' => $workOrderRef,
                'hours_worked' => $hoursWorked,
                'allocated_cost_idr' => $cost,
            ]);
        });
    }

    public function auditHcm(): array
    {
        $payrolls = Payroll::all();
        $discrepancies = 0;

        foreach ($payrolls as $p) {
            $computedNet = $p->gross_salary_idr - $p->deductions_idr;
            if ($computedNet !== $p->net_salary_idr) {
                $discrepancies++;
            }
            $expectedDeductions = $p->pph21_idr + $p->bpjs_tk_idr + $p->bpjs_kes_idr;
            if ($expectedDeductions !== $p->deductions_idr) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'payroll_count' => $payrolls->count(),
            'employee_count' => Employee::count(),
            'discrepancies' => $discrepancies,
        ];
    }
}
