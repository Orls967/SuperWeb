<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Tests\TestCase;

class SalaryAuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $hcmManager;

    private User $staffUser;

    private User $otherStaffUser;

    private Employee $staffEmployee;

    private Employee $otherEmployee;

    private Payroll $staffPayroll;

    private Payroll $otherPayroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->admin->assignRbacRole('admin');

        $this->hcmManager = User::factory()->create([
            'role' => 'hcm_manager',
        ]);
        $this->hcmManager->assignRbacRole('hcm_manager');

        $this->staffUser = User::factory()->create([
            'email' => 'budi.staff@autoserve.test',
            'role' => 'operator',
        ]);
        $this->staffUser->assignRbacRole('operator');

        $this->otherStaffUser = User::factory()->create([
            'email' => 'siti.staff@autoserve.test',
            'role' => 'operator',
        ]);
        $this->otherStaffUser->assignRbacRole('operator');

        $department = Department::create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $this->staffEmployee = Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->staffUser->id,
            'employee_number' => 'EMP-001',
            'name' => 'Budi Staff',
            'email' => $this->staffUser->email,
            'department_id' => $department->id,
            'position' => 'Machine Operator',
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 6000000,
            'allowances_idr' => 1000000,
            'is_active' => true,
        ]);

        $this->otherEmployee = Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->otherStaffUser->id,
            'employee_number' => 'EMP-002',
            'name' => 'Siti Staff',
            'email' => $this->otherStaffUser->email,
            'department_id' => $department->id,
            'position' => 'Senior Technician',
            'employment_type' => 'PKWTT',
            'basic_salary_idr' => 9000000,
            'allowances_idr' => 1500000,
            'is_active' => true,
        ]);

        $this->staffPayroll = Payroll::create([
            'id' => (string) Str::uuid(),
            'employee_id' => $this->staffEmployee->id,
            'period' => '2026-10',
            'gross_salary_idr' => 7000000,
            'deductions_idr' => 350000,
            'net_salary_idr' => 6650000,
            'status' => 'paid',
        ]);

        $this->otherPayroll = Payroll::create([
            'id' => (string) Str::uuid(),
            'employee_id' => $this->otherEmployee->id,
            'period' => '2026-10',
            'gross_salary_idr' => 10500000,
            'deductions_idr' => 525000,
            'net_salary_idr' => 9975000,
            'status' => 'paid',
        ]);
    }

    public function test_admin_can_view_all_salaries_and_payrolls(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('view-any-salary'));
        $this->assertTrue($this->admin->can('viewSalary', $this->staffEmployee));
        $this->assertTrue($this->admin->can('viewSalary', $this->otherEmployee));

        $this->assertTrue(Gate::forUser($this->admin)->allows('view-payroll', $this->staffPayroll));
        $this->assertTrue(Gate::forUser($this->admin)->allows('view-payroll', $this->otherPayroll));
        $this->assertTrue($this->admin->can('view', $this->staffPayroll));
        $this->assertTrue($this->admin->can('view', $this->otherPayroll));

        $this->actingAs($this->admin)
            ->get(route('hcm.index'))
            ->assertOk();
    }

    public function test_hcm_manager_can_view_all_salaries_and_payrolls(): void
    {
        $this->assertTrue(Gate::forUser($this->hcmManager)->allows('view-any-salary'));
        $this->assertTrue($this->hcmManager->can('viewSalary', $this->staffEmployee));
        $this->assertTrue($this->hcmManager->can('viewSalary', $this->otherEmployee));

        $this->assertTrue(Gate::forUser($this->hcmManager)->allows('view-payroll', $this->staffPayroll));
        $this->assertTrue(Gate::forUser($this->hcmManager)->allows('view-payroll', $this->otherPayroll));
        $this->assertTrue($this->hcmManager->can('view', $this->staffPayroll));
        $this->assertTrue($this->hcmManager->can('view', $this->otherPayroll));

        $this->actingAs($this->hcmManager)
            ->get(route('hcm.index'))
            ->assertOk();
    }

    public function test_staff_cannot_view_other_employees_salaries_or_payrolls(): void
    {
        // 1. Staff cannot view global salary list
        $this->assertFalse(Gate::forUser($this->staffUser)->allows('view-any-salary'));
        $this->assertFalse($this->staffUser->can('viewAnySalary', Employee::class));

        // 2. Staff cannot view other employee's salary or payroll slip
        $this->assertFalse($this->staffUser->can('viewSalary', $this->otherEmployee));
        $this->assertFalse(Gate::forUser($this->staffUser)->allows('view-salary', $this->otherEmployee));
        $this->assertFalse($this->staffUser->can('view', $this->otherPayroll));
        $this->assertFalse(Gate::forUser($this->staffUser)->allows('view-payroll', $this->otherPayroll));

        // 3. Staff is strictly forbidden from accessing HCM module dashboard
        $this->actingAs($this->staffUser)
            ->get(route('hcm.index'))
            ->assertForbidden();
    }

    public function test_staff_can_view_own_salary_and_payroll_slip(): void
    {
        // Staff can inspect their own salary record
        $this->assertTrue($this->staffUser->can('viewSalary', $this->staffEmployee));
        $this->assertTrue(Gate::forUser($this->staffUser)->allows('view-salary', $this->staffEmployee));

        // Staff can inspect their own payroll slip
        $this->assertTrue($this->staffUser->can('view', $this->staffPayroll));
        $this->assertTrue(Gate::forUser($this->staffUser)->allows('view-payroll', $this->staffPayroll));
    }
}
