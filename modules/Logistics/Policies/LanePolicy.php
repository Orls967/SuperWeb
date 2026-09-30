<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;

class LanePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher();
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
