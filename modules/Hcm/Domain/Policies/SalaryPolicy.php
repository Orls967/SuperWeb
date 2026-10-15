<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Policies;

use App\Models\User;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;

class SalaryPolicy
{
    /**
     * Determine whether the user can view any employee salaries.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRbacRole(['admin', 'hcm_manager']);
    }

    /**
     * Determine whether the user can view a specific employee's salary.
     */
    public function view(User $user, Employee $employee): bool
    {
        if ($user->hasAnyRbacRole(['admin', 'hcm_manager'])) {
            return true;
        }

        return ($employee->user_id !== null && $employee->user_id === $user->id)
            || ($employee->email !== null && $employee->email === $user->email);
    }

    /**
     * Determine whether the user can view a specific payroll slip.
     */
    public function viewPayroll(User $user, Payroll $payroll): bool
    {
        if ($user->hasAnyRbacRole(['admin', 'hcm_manager'])) {
            return true;
        }

        $employee = $payroll->employee;
        if (! $employee) {
            return false;
        }

        return ($employee->user_id !== null && $employee->user_id === $user->id)
            || ($employee->email !== null && $employee->email === $user->email);
    }
}
