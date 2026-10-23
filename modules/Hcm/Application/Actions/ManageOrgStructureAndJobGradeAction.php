<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Hcm\Domain\Models\Department;
use Modules\Hcm\Domain\Models\Employee;

class ManageOrgStructureAndJobGradeAction
{
    /**
     * Create or update organizational department structure.
     *
     * @param array{
     *     code: string,
     *     name: string,
     *     parent_id?: string|null,
     *     is_active?: bool
     * } $data
     */
    public function manageDepartment(array $data): Department
    {
        if (empty($data['code']) || empty($data['name'])) {
            throw new InvalidArgumentException('Department code and name are required.');
        }

        return Department::updateOrCreate(
            ['code' => $data['code']],
            [
                'name' => $data['name'],
                'parent_id' => $data['parent_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]
        );
    }

    /**
     * Assign employee job grade, department, and salary bandwidth structure.
     *
     * @param array{
     *     name: string,
     *     employee_number?: string,
     *     position: string,
     *     department_id?: string|null,
     *     employment_type?: string,
     *     basic_salary_idr: int,
     *     allowances_idr?: int,
     *     bank_name?: string,
     *     bank_account_number?: string,
     *     email?: string|null
     * } $data
     */
    public function assignEmployeeJobGrade(array $data): Employee
    {
        if ($data['basic_salary_idr'] <= 0) {
            throw new InvalidArgumentException('Basic salary must be greater than zero.');
        }

        $empNumber = $data['employee_number'] ?? 'EMP-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return Employee::updateOrCreate(
            ['employee_number' => $empNumber],
            [
                'id' => (string) Str::uuid(),
                'name' => $data['name'],
                'position' => $data['position'],
                'department_id' => $data['department_id'] ?? null,
                'employment_type' => $data['employment_type'] ?? 'PKWTT',
                'basic_salary_idr' => $data['basic_salary_idr'],
                'allowances_idr' => $data['allowances_idr'] ?? 0,
                'bank_name' => $data['bank_name'] ?? 'Bank Mandiri',
                'bank_account_number' => $data['bank_account_number'] ?? '123-00-'.rand(1000000, 9999999),
                'email' => $data['email'] ?? null,
                'is_active' => true,
            ]
        );
    }
}
