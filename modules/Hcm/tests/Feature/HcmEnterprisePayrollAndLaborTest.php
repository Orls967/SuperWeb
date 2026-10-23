<?php

declare(strict_types=1);

namespace Modules\Hcm\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Hcm\Application\Actions\AllocateDirectLaborCostToManufacturingAction;
use Modules\Hcm\Application\Actions\CalculateEnterprisePayrollWithPph21AndBpjsAction;
use Modules\Hcm\Application\Actions\ManageOrgStructureAndJobGradeAction;
use Modules\Hcm\Application\Actions\RecordBiometricShiftAttendanceAction;
use Modules\Hcm\Application\Actions\RunHcmPayrollReconciliationAuditAction;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\ShiftSchedule;
use Tests\TestCase;

class HcmEnterprisePayrollAndLaborTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Department $dept;

    protected Employee $employee;

    protected ShiftSchedule $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->dept = Department::create([
            'code' => 'OPS-PLANT',
            'name' => 'Assembly Plant Operations',
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'employee_number' => 'EMP-OPS-001',
            'name' => 'Ahmad Fauzi',
            'position' => 'Senior Assembly Technician',
            'department_id' => $this->dept->id,
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 10000000,
            'allowances_idr' => 2000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '137-00-9988771',
            'is_active' => true,
        ]);

        $this->shift = ShiftSchedule::create([
            'shift_code' => 'SHIFT-MORNING',
            'name' => 'Morning Production Shift',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'is_night_shift' => false,
        ]);
    }

    public function test_manage_department_hierarchy_creation_and_update(): void
    {
        $action = app(ManageOrgStructureAndJobGradeAction::class);

        $parentDept = $action->manageDepartment([
            'code' => 'DIV-MFG',
            'name' => 'Manufacturing Division',
        ]);

        $subDept = $action->manageDepartment([
            'code' => 'DEP-QA',
            'name' => 'Quality Assurance Sub-department',
            'parent_id' => $parentDept->id,
        ]);

        $this->assertNotNull($parentDept->id);
        $this->assertNotNull($subDept->id);
        $this->assertSame($parentDept->id, $subDept->parent_id);
    }

    public function test_assign_employee_job_grade_compensation_structure(): void
    {
        $action = app(ManageOrgStructureAndJobGradeAction::class);

        $emp = $action->assignEmployeeJobGrade([
            'employee_number' => 'EMP-TECH-77',
            'name' => 'Siti Rahma',
            'position' => 'Robotics Calibration Specialist',
            'department_id' => $this->dept->id,
            'basic_salary_idr' => 15000000,
            'allowances_idr' => 3000000,
        ]);

        $this->assertNotNull($emp->id);
        $this->assertSame('EMP-TECH-77', $emp->employee_number);
        $this->assertSame(15000000, $emp->basic_salary_idr);
        $this->assertSame(3000000, $emp->allowances_idr);
    }

    public function test_record_biometric_shift_attendance_on_time(): void
    {
        $action = app(RecordBiometricShiftAttendanceAction::class);

        $res = $action->execute(
            $this->employee,
            '2026-10-15',
            '2026-10-15 07:55:00',
            '2026-10-15 17:00:00',
            $this->shift
        );

        $this->assertSame(0, $res['late_minutes']);
        $this->assertSame(0.0, $res['overtime_hours']);
        $this->assertSame('present', $res['status']);
    }

    public function test_record_biometric_shift_attendance_late_and_overtime(): void
    {
        $action = app(RecordBiometricShiftAttendanceAction::class);

        // Clock in 08:25 (25 mins late), clock out 19:30 (2.5 hrs overtime)
        $res = $action->execute(
            $this->employee,
            '2026-10-16',
            '2026-10-16 08:25:00',
            '2026-10-16 19:30:00',
            $this->shift
        );

        $this->assertSame(25, $res['late_minutes']);
        $this->assertSame(2.5, $res['overtime_hours']);
        $this->assertSame('late', $res['status']);
    }

    public function test_calculate_enterprise_payroll_with_pph21_and_bpjs(): void
    {
        $action = app(CalculateEnterprisePayrollWithPph21AndBpjsAction::class);

        // Basic: 10,000,000. Allowances: 2,000,000. Gross = 12,000,000
        // PPh21 TER (between 10M and 20M) = 5% of 12,000,000 = 600,000
        // BPJS Kes Employee (1% capped at 12M) = 120,000
        // BPJS TK JHT Employee (2% of 12M) = 240,000
        // BPJS TK JP Employee (1% capped at 10,042,300) = 100,423
        // Total TK Employee = 340,423
        // Total Deductions = 600,000 + 120,000 + 340,423 = 1,060,423
        // Net = 12,000,000 - 1,060,423 = 10,939,577
        $res = $action->execute($this->employee, '2026-10');

        $this->assertSame(12000000, $res['gross_salary_idr']);
        $this->assertSame(600000, $res['pph21_idr']);
        $this->assertSame(120000, $res['bpjs_kes_employee_idr']);
        $this->assertSame(340423, $res['bpjs_tk_employee_idr']);
        $this->assertSame(1060423, $res['total_deductions_idr']);
        $this->assertSame(10939577, $res['net_salary_idr']);
        $this->assertNotNull($res['payroll']->id);
        $this->assertSame('approved', $res['payroll']->status);
    }

    public function test_calculate_enterprise_payroll_validates_basic_salary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $action = app(CalculateEnterprisePayrollWithPph21AndBpjsAction::class);

        $invalidEmp = Employee::create([
            'employee_number' => 'EMP-ZERO-SAL',
            'name' => 'Zero Salary Emp',
            'position' => 'Volunteer',
            'basic_salary_idr' => 0,
        ]);

        $action->execute($invalidEmp, '2026-10');
    }

    public function test_allocate_direct_labor_cost_to_manufacturing_work_order(): void
    {
        $payrollAction = app(CalculateEnterprisePayrollWithPph21AndBpjsAction::class);
        $payrollRes = $payrollAction->execute($this->employee, '2026-10');

        $allocAction = app(AllocateDirectLaborCostToManufacturingAction::class);
        // Gross: 12,000,000. Hourly: round(12M / 173) = 69,364 IDR/hr.
        // 20 hours -> 20 * 69,364 = 1,387,280 IDR.
        $allocation = $allocAction->execute($payrollRes['payroll'], 'WO-PLANT-991', 20.0);

        $this->assertNotNull($allocation->id);
        $this->assertSame('WO-PLANT-991', $allocation->work_order_ref);
        $this->assertSame(20.0, (float) $allocation->hours_worked);
        $this->assertSame(1387280, $allocation->allocated_cost_idr);
    }

    public function test_allocate_direct_labor_cost_validates_positive_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $payrollAction = app(CalculateEnterprisePayrollWithPph21AndBpjsAction::class);
        $payrollRes = $payrollAction->execute($this->employee, '2026-10');

        $allocAction = app(AllocateDirectLaborCostToManufacturingAction::class);
        $allocAction->execute($payrollRes['payroll'], 'WO-FAIL', 0.0);
    }

    public function test_run_hcm_payroll_reconciliation_audit_passes(): void
    {
        $payrollAction = app(CalculateEnterprisePayrollWithPph21AndBpjsAction::class);
        $payrollAction->execute($this->employee, '2026-10');

        $auditAction = app(RunHcmPayrollReconciliationAuditAction::class);
        $audit = $auditAction->execute('2026-10');

        $this->assertTrue($audit['reconciliation_passed']);
        $this->assertEmpty($audit['discrepancies']);
        $this->assertSame(1, $audit['total_employees']);
        $this->assertSame(12000000, $audit['total_gross_idr']);
        $this->assertSame(1060423, $audit['total_deductions_idr']);
        $this->assertSame(10939577, $audit['total_net_idr']);
    }

    public function test_hcm_enterprise_operations_controller_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Cockpit Index
        $respDash = $this->get('/hcm/operations');
        $respDash->assertOk();

        // 2. Department API
        $respDept = $this->postJson('/hcm/operations/department', [
            'code' => 'DEP-LOG-01',
            'name' => 'Internal Plant Logistics',
        ]);
        $respDept->assertStatus(201);
        $this->assertSame('DEP-LOG-01', $respDept->json('department.code'));

        // 3. Job Grade API
        $respEmp = $this->postJson('/hcm/operations/job-grade', [
            'name' => 'Dewi Lestari',
            'position' => 'Production Supervisor',
            'basic_salary_idr' => 14000000,
            'allowances_idr' => 2500000,
        ]);
        $respEmp->assertStatus(201);
        $empId = $respEmp->json('employee.id');

        // 4. Attendance API
        $respAtt = $this->postJson('/hcm/operations/attendance', [
            'employee_id' => $empId,
            'shift_id' => $this->shift->id,
            'log_date' => '2026-10-18',
            'clock_in' => '2026-10-18 08:00:00',
            'clock_out' => '2026-10-18 17:00:00',
        ]);
        $respAtt->assertStatus(201);
        $this->assertSame('present', $respAtt->json('data.status'));

        // 5. Calculate Payroll API
        $respPay = $this->postJson("/hcm/operations/payroll/{$empId}/calculate", [
            'period' => '2026-10',
            'bonus_idr' => 1000000,
        ]);
        $respPay->assertStatus(201);
        $payrollId = $respPay->json('data.payroll.id');
        $this->assertNotNull($payrollId);

        // 6. Allocate Labor API
        $respAlloc = $this->postJson("/hcm/operations/labor-allocation/{$payrollId}", [
            'work_order_ref' => 'WO-CHASSIS-101',
            'hours_worked' => 15.5,
        ]);
        $respAlloc->assertStatus(201);
        $this->assertSame('WO-CHASSIS-101', $respAlloc->json('allocation.work_order_ref'));

        // 7. Audit Reconcile API
        $respAudit = $this->postJson('/hcm/operations/audit/reconcile', [
            'period' => '2026-10',
        ]);
        $respAudit->assertOk();
        $this->assertTrue($respAudit->json('audit.reconciliation_passed'));
    }
}
