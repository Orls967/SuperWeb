<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher();
    }

    public function view(User $user, mixed $driver = null): bool
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()) {
            return true;
        }

        if ($driver && isset($driver->user_id) && $user->id === $driver->user_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin();
    }
}
