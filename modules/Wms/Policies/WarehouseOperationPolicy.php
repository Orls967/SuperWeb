<?php

declare(strict_types=1);

namespace Modules\Wms\Policies;

use App\Models\User;

/**
 * Otorisasi operasi gudang WMS (admin, hub_operator, procurement, auditor, logistics_admin).
 */
final class WarehouseOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hub_operator', 'procurement', 'auditor', 'logistics_admin'], true);
    }

    public function executeTask(User $user): bool
    {
        return in_array($user->role, ['admin', 'hub_operator', 'logistics_admin'], true);
    }

    public function revalueCost(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function quarantineStock(User $user): bool
    {
        return in_array($user->role, ['admin', 'hub_operator', 'auditor'], true);
    }
}
