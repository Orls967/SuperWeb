<?php

declare(strict_types=1);

namespace Modules\ControlTower\Policies;

use App\Models\User;

final class ControlTowerOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'logistics_admin', 'planner', 'auditor'], true);
    }

    public function manageSop(User $user): bool
    {
        return in_array($user->role, ['admin', 'logistics_admin', 'planner'], true);
    }

    public function overrideForecast(User $user): bool
    {
        return in_array($user->role, ['admin', 'logistics_admin', 'planner'], true);
    }

    public function runTwin(User $user): bool
    {
        return in_array($user->role, ['admin', 'logistics_admin', 'planner'], true);
    }

    public function manageAlerts(User $user): bool
    {
        return in_array($user->role, ['admin', 'logistics_admin', 'planner'], true);
    }
}
