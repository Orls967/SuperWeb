<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;

class LocationPolicy
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
        return $user->isAdmin() || $user->isLogisticsAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin() || $user->isLogisticsAdmin();
    }
}
