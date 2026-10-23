<?php

declare(strict_types=1);

namespace Modules\Hcm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hcm\Application\Actions\AllocateDirectLaborCostToManufacturingAction;
use Modules\Hcm\Application\Actions\CalculateEnterprisePayrollWithPph21AndBpjsAction;
use Modules\Hcm\Application\Actions\ManageOrgStructureAndJobGradeAction;
use Modules\Hcm\Application\Actions\RecordBiometricShiftAttendanceAction;
use Modules\Hcm\Application\Actions\RunHcmPayrollReconciliationAuditAction;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Domain\Models\ShiftSchedule;

class HcmEnterpriseOperationsController extends Controller
{
    public function index(): mixed
    {
        $departments = Department::where('is_active', true)->get();
        $employees = Employee::latest()->take(15)->get();
        $payrolls = Payroll::with('employee')->latest()->take(15)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'departments' => $departments,
                'employees' => $employees,
                'payrolls' => $payrolls,
            ]);
        }

        return view('hcm::hcm.hcm_cockpit', compact('departments', 'employees', 'payrolls'));
    }

    public function createDepartment(
        Request $request,
        ManageOrgStructureAndJobGradeAction $action
    ): JsonResponse {
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'required|string|max:120',
            'parent_id' => 'nullable|uuid',
            'is_active' => 'nullable|boolean',
        ]);

        $dept = $action->manageDepartment($data);

        return response()->json([
            'success' => true,
            'message' => 'Department organizational unit created/updated.',
            'department' => $dept,
        ], 201);
    }

    public function assignJobGrade(
        Request $request,
        ManageOrgStructureAndJobGradeAction $action
    ): JsonResponse {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'employee_number' => 'nullable|string|max:40',
            'position' => 'required|string|max:100',
            'department_id' => 'nullable|uuid',
            'employment_type' => 'nullable|string|in:PKWT,PKWTT,casual',
            'basic_salary_idr' => 'required|integer|min:1',
            'allowances_idr' => 'nullable|integer|min:0',
            'bank_name' => 'nullable|string|max:60',
            'bank_account_number' => 'nullable|string|max:60',
            'email' => 'nullable|email|max:120',
        ]);

        $emp = $action->assignEmployeeJobGrade($data);

        return response()->json([
            'success' => true,
            'message' => 'Employee job grade and compensation band configured.',
            'employee' => $emp,
        ], 201);
    }

    public function recordAttendance(
        Request $request,
        RecordBiometricShiftAttendanceAction $action
    ): JsonResponse {
        $data = $request->validate([
            'employee_id' => 'required|uuid',
            'shift_id' => 'nullable|uuid',
            'log_date' => 'required|date',
            'clock_in' => 'required|string',
            'clock_out' => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $shift = ! empty($data['shift_id']) ? ShiftSchedule::find($data['shift_id']) : null;

        $result = $action->execute(
            $employee,
            $data['log_date'],
            $data['clock_in'],
            $data['clock_out'] ?? null,
            $shift
        );

        return response()->json([
            'success' => true,
            'message' => 'Biometric shift attendance recorded.',
            'data' => $result,
        ], 201);
    }

    public function calculatePayroll(
        Request $request,
        Employee $employee,
        CalculateEnterprisePayrollWithPph21AndBpjsAction $action
    ): JsonResponse {
        $data = $request->validate([
            'period' => 'required|string|size:7',
            'bonus_idr' => 'nullable|integer|min:0',
        ]);

        $result = $action->execute(
            $employee,
            $data['period'],
            (int) ($data['bonus_idr'] ?? 0)
        );

        return response()->json([
            'success' => true,
            'message' => 'Enterprise payroll calculated and posted to ledger.',
            'data' => $result,
        ], 201);
    }

    public function allocateLabor(
        Request $request,
        Payroll $payroll,
        AllocateDirectLaborCostToManufacturingAction $action
    ): JsonResponse {
        $data = $request->validate([
            'work_order_ref' => 'required|string|max:60',
            'hours_worked' => 'required|numeric|min:0.1',
            'custom_hourly_rate_idr' => 'nullable|integer|min:1',
        ]);

        $allocation = $action->execute(
            $payroll,
            $data['work_order_ref'],
            (float) $data['hours_worked'],
            isset($data['custom_hourly_rate_idr']) ? (int) $data['custom_hourly_rate_idr'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Direct labor allocated to manufacturing work order.',
            'allocation' => $allocation,
        ], 201);
    }

    public function runReconciliationAudit(
        Request $request,
        RunHcmPayrollReconciliationAuditAction $action
    ): JsonResponse {
        $data = $request->validate([
            'period' => 'required|string|size:7',
        ]);

        $result = $action->execute($data['period']);

        return response()->json([
            'success' => true,
            'audit' => $result,
        ]);
    }
}
