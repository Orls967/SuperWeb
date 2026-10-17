<?php

declare(strict_types=1);

namespace Modules\Hcm\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Hcm\Domain\Models\AttendanceLog;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\LeaveRequest;
use Modules\Hcm\Domain\Models\ShiftSchedule;
use Tests\TestCase;

class HcmTest extends TestCase
{
    use RefreshDatabase;

    protected HcmService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(HcmService::class);
    }

    public function test_can_register_employee_and_generate_payroll(): void
    {
        $dept = Department::create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-001',
            'name' => 'Budi Santoso',
            'nik' => '3171012345670001',
            'email' => 'budi@autoserve.test',
            'department_id' => $dept->id,
            'position' => 'Senior Engineer',
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 12000000,
            'allowances_idr' => 3000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876543',
        ]);

        $this->assertDatabaseHas('hcm_employees', [
            'id' => $employee->id,
            'employee_number' => 'EMP-001',
            'basic_salary_idr' => 12000000,
        ]);

        $payroll = $this->service->generatePayroll($employee, '2026-10');

        $this->assertSame(15000000, $payroll->gross_salary_idr);
        $this->assertSame($payroll->gross_salary_idr - $payroll->deductions_idr, $payroll->net_salary_idr);
        $this->assertGreaterThan(0, $payroll->pph21_idr);
        $this->assertGreaterThan(0, $payroll->bpjs_tk_idr);
        $this->assertGreaterThan(0, $payroll->bpjs_kes_idr);
    }

    public function test_can_manage_shifts_attendance_and_overtime_calculation(): void
    {
        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-SHIFT-01',
            'name' => 'Rian Factory',
            'position' => 'Line Operator',
            'basic_salary_idr' => 5190000, // 5.19M / 173 = 30k/hr
            'allowances_idr' => 1000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876549',
        ]);

        $shift = $this->service->createShift([
            'shift_code' => 'SHIFT-PAGI',
            'name' => 'Shift Pagi 07:00-15:00',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'break_minutes' => 60,
            'is_night_shift' => false,
        ]);

        $this->assertInstanceOf(ShiftSchedule::class, $shift);

        $attendance = $this->service->recordAttendance([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'log_date' => '2026-10-01',
            'clock_in' => '2026-10-01 06:55:00',
            'clock_out' => '2026-10-01 17:00:00',
            'late_minutes' => 0,
            'overtime_hours' => 2.0,
            'status' => 'present',
        ]);

        $this->assertInstanceOf(AttendanceLog::class, $attendance);
        $this->assertSame(2.0, (float) $attendance->overtime_hours);

        $payroll = $this->service->generatePayroll($employee, '2026-10');
        // Overtime: 2 hrs * 30,000 * 1.5 = 90,000 IDR
        $this->assertSame(5190000 + 1000000 + 90000, $payroll->gross_salary_idr);
    }

    public function test_can_submit_and_approve_leave_request(): void
    {
        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-LEAVE-01',
            'name' => 'Siti Cuti',
            'basic_salary_idr' => 7000000,
            'allowances_idr' => 1000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876548',
        ]);

        $approver = $this->service->registerEmployee([
            'employee_number' => 'EMP-MGR-01',
            'name' => 'Pak Manager',
            'basic_salary_idr' => 15000000,
            'allowances_idr' => 5000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876547',
        ]);

        $leave = $this->service->submitLeaveRequest([
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-17',
            'total_days' => 3,
            'reason' => 'Family vacation',
        ]);

        $this->assertInstanceOf(LeaveRequest::class, $leave);
        $this->assertSame('pending', $leave->status);

        $approved = $this->service->approveLeaveRequest($leave->id, $approver->id);
        $this->assertSame('approved', $approved->status);
        $this->assertSame($approver->id, $approved->approved_by);
    }

    public function test_can_allocate_labor_cost_to_production(): void
    {
        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-002',
            'name' => 'Agus Operator',
            'position' => 'Line Operator',
            'basic_salary_idr' => 6000000,
            'allowances_idr' => 1000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876544',
        ]);

        $payroll = $this->service->generatePayroll($employee, '2026-10');
        $allocation = $this->service->allocateLaborToProduction($payroll, 'MPO/JKT/2026/001', 40.0, 50000);

        $this->assertSame(2000000, $allocation->allocated_cost_idr);
        $this->assertDatabaseHas('hcm_production_labor_allocations', [
            'id' => $allocation->id,
            'work_order_ref' => 'MPO/JKT/2026/001',
        ]);
    }

    public function test_hcm_audit_passes_with_zero_discrepancy(): void
    {
        $employee = $this->service->registerEmployee([
            'employee_number' => 'EMP-003',
            'name' => 'Dewi Finance',
            'basic_salary_idr' => 8000000,
            'allowances_idr' => 2000000,
            'bank_name' => 'Bank Mandiri',
            'bank_account_number' => '1230009876545',
        ]);

        $this->service->generatePayroll($employee, '2026-10');

        $exitCode = Artisan::call('hcm:audit');
        $this->assertSame(0, $exitCode);
    }

    public function test_hcm_web_index_accessible(): void
    {
        $user = User::factory()->create(['role' => 'hcm_manager']);

        $response = $this->actingAs($user)->get(route('hcm.index'));
        $response->assertStatus(200);
        $response->assertSee('Human Capital Management');
    }
}
