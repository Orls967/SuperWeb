<?php

declare(strict_types=1);

namespace Modules\Resto\Policies;

use App\Models\User;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\RestoStaffAssignment;

class OutletPolicy
{
    public function view(User $user, Outlet $outlet): bool
    {
        if (in_array($user->role, ['admin', 'auditor'], true)) {
            return true;
        }

        if ($user->role === 'outlet_manager') {
            return RestoStaffAssignment::where('user_id', $user->id)
                ->where('outlet_id', $outlet->id)
                ->where('is_active', true)
                ->exists();
        }

        return false;
    }

    public function update(User $user, Outlet $outlet): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'outlet_manager') {
            return RestoStaffAssignment::where('user_id', $user->id)
                ->where('outlet_id', $outlet->id)
                ->where('is_active', true)
                ->exists();
        }

        return false;
    }
}
