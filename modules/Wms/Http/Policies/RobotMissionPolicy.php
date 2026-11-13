<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Policies;

use App\Models\User;
use Modules\Wms\Models\RobotMission;

/**
 * Policy anti-IDOR untuk akses misi robotika berdasarkan peran dan penugasan gudang (U-5).
 */
final class RobotMissionPolicy
{
    public function view(User $user, RobotMission $mission): bool
    {
        if ($user->hasAnyRole(['admin', 'auditor', 'procurement'])) {
            return true;
        }

        if ($user->hasRole('hub_operator')) {
            return $mission->assigned_operator_user_id === null
                || (int) $mission->assigned_operator_user_id === (int) $user->id;
        }

        return false;
    }

    public function fallback(User $user, RobotMission $mission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('hub_operator')
            && ($mission->assigned_operator_user_id === null || (int) $mission->assigned_operator_user_id === (int) $user->id);
    }
}
