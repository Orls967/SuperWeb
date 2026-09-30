<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;

class FleetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher() || $user->isHubOperator();
    }

    public function view(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher() || $user->isHubOperator();
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
