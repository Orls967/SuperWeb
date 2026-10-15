<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Policies;

use App\Models\User;
use Modules\Hcm\Domain\Models\Payroll;

class PayrollPolicy
{
    /**
     * Determine whether the user can view any payrolls.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRbacRole(['admin', 'hcm_manager']);
    }

    /**
     * Determine whether the user can view a specific payroll slip.
     */
    public function view(User $user, Payroll $payroll): bool
    {
        if ($user->hasAnyRbacRole(['admin', 'hcm_manager'])) {
            return true;
        }

        $employee = $payroll->employee;
        if (! $employee) {
            return false;
        }

        // Karyawan biasa hanya dapat melihat slip miliknya sendiri
        return ($employee->user_id !== null && $employee->user_id === $user->id)
            || ($employee->email !== null && $employee->email === $user->email);
    }
}
