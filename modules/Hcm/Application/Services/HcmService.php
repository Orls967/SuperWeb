<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use App\Security\BlindIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hcm\Domain\Models\AttendanceLog;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\LeaveRequest;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Domain\Models\ProductionLaborAllocation;
use Modules\Hcm\Domain\Models\ShiftSchedule;
use Modules\Hcm\Ledger\HcmTransactionTypes;

class HcmService
{
    protected LedgerService $ledgerService;

    public function __construct(?LedgerService $ledgerService = null)
    {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

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

    public function createShift(array $data): ShiftSchedule
    {
        return DB::transaction(function () use ($data) {
            return ShiftSchedule::create([
                'shift_code' => $data['shift_code'] ?? 'SHIFT-'.strtoupper(Str::random(4)),
                'name' => $data['name'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'break_minutes' => (int) ($data['break_minutes'] ?? 60),
                'is_night_shift' => (bool) ($data['is_night_shift'] ?? false),
            ]);
        });
    }

    public function recordAttendance(array $data): AttendanceLog
    {
        return DB::transaction(function () use ($data) {
            return AttendanceLog::updateOrCreate(
                [
                    'employee_id' => $data['employee_id'],
                    'log_date' => $data['log_date'],
                ],
                [
                    'shift_id' => $data['shift_id'] ?? null,
                    'clock_in' => $data['clock_in'] ?? null,
                    'clock_out' => $data['clock_out'] ?? null,
                    'late_minutes' => (int) ($data['late_minutes'] ?? 0),
                    'overtime_hours' => (float) ($data['overtime_hours'] ?? 0.00),
                    'status' => $data['status'] ?? 'present',
                ]
            );
        });
    }

    public function submitLeaveRequest(array $data): LeaveRequest
    {
        return DB::transaction(function () use ($data) {
            return LeaveRequest::create([
                'employee_id' => $data['employee_id'],
                'leave_type' => $data['leave_type'] ?? 'annual',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => (int) ($data['total_days'] ?? 1),
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
            ]);
        });
    }

    public function approveLeaveRequest(string $requestId, string $approverId): LeaveRequest
    {
        return DB::transaction(function () use ($requestId, $approverId) {
            $leave = LeaveRequest::where('id', $requestId)->lockForUpdate()->firstOrFail();
            $leave->update([
                'status' => 'approved',
                'approved_by' => $approverId,
            ]);

            return $leave;
        });
    }

    public function generatePayroll(Employee $employee, string $period): Payroll
    {
        return DB::transaction(function () use ($employee, $period) {
            // Overtime from attendance logs
            $overtimeHours = (float) AttendanceLog::where('employee_id', $employee->id)
                ->where('log_date', 'like', "{$period}%")
                ->sum('overtime_hours');

            $hourlyWage = $employee->basic_salary_idr > 0 ? (int) round($employee->basic_salary_idr / 173) : 0;
            $overtimePay = (int) round($overtimeHours * $hourlyWage * 1.5);

            $gross = $employee->basic_salary_idr + $employee->allowances_idr + $overtimePay;

            // BPJS & PPh21 TER (Aritmetika integer half-up)
            $bpjsTk = intdiv($gross * 3 + 50, 100);
            $bpjsKes = intdiv($gross * 1 + 50, 100);
            $pph21 = intdiv($gross * 5 + 50, 100);

            $deductions = $bpjsTk + $bpjsKes + $pph21;
            $net = $gross - $deductions;

            $payroll = Payroll::updateOrCreate(
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

            // Double-entry ledger journal posting
            if ($gross > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: HcmTransactionTypes::HCM_PAYROLL_POSTING->value,
                    description: "Payroll for {$employee->name} ({$employee->employee_number}) period {$period}",
                    idempotencyKey: "HCM-PAY-{$payroll->id}-{$period}",
                    entries: Posting::lines()
                        ->debit('expense:hcm:salaries:IDR', $gross)
                        ->credit('tax:hcm:pph21_payable:IDR', $pph21)
                        ->credit('ap:hcm:bpjs_payable:IDR', $bpjsTk + $bpjsKes)
                        ->credit('ap:hcm:payroll_payable:IDR', $net)
                        ->toEntries('IDR'),
                    referenceType: 'HCM_PAYROLL',
                    referenceId: (string) $payroll->id,
                ));
            }

            return $payroll;
        });
    }

    public function allocateLaborToProduction(Payroll $payroll, string $workOrderRef, float $hoursWorked, int $hourlyRateIdr): ProductionLaborAllocation
    {
        return DB::transaction(function () use ($payroll, $workOrderRef, $hoursWorked, $hourlyRateIdr) {
            $cost = intdiv((int) round($hoursWorked * 100) * $hourlyRateIdr, 100);

            $allocation = ProductionLaborAllocation::create([
                'id' => (string) Str::uuid(),
                'payroll_id' => $payroll->id,
                'work_order_ref' => $workOrderRef,
                'hours_worked' => $hoursWorked,
                'allocated_cost_idr' => $cost,
            ]);

            if ($cost > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: HcmTransactionTypes::HCM_LABOR_ALLOCATION->value,
                    description: "Labor allocation for WO {$workOrderRef} ({$hoursWorked} hrs)",
                    idempotencyKey: "HCM-LABOR-{$allocation->id}",
                    entries: Posting::lines()
                        ->debit('mfg:labor_wip:IDR', $cost)
                        ->credit('ap:hcm:payroll_payable:IDR', $cost)
                        ->toEntries('IDR'),
                    referenceType: 'PRODUCTION_LABOR_ALLOCATION',
                    referenceId: (string) $allocation->id,
                ));
            }

            return $allocation;
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
            'shift_count' => ShiftSchedule::count(),
            'attendance_count' => AttendanceLog::count(),
            'discrepancies' => $discrepancies,
        ];
    }
}
