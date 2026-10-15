<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Policies;

use App\Models\User;
use Modules\Hcm\Domain\Models\Employee;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any employee salaries.
     */
    public function viewAnySalary(User $user): bool
    {
        return $user->hasAnyRbacRole(['admin', 'hcm_manager']);
    }

    /**
     * Determine whether the user can view a specific employee's salary.
     */
    public function viewSalary(User $user, Employee $employee): bool
    {
        if ($user->hasAnyRbacRole(['admin', 'hcm_manager'])) {
            return true;
        }

        // Karyawan biasa hanya dapat melihat data miliknya sendiri
        return ($employee->user_id !== null && $employee->user_id === $user->id)
            || ($employee->email !== null && $employee->email === $user->email);
    }
}
