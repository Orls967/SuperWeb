<?php

declare(strict_types=1);

namespace Modules\Distribution\Policies;

use App\Models\User;
use Modules\Distribution\Domain\Models\Distributor;

class DistributorPolicy
{
    public function view(User $user, Distributor $distributor): bool
    {
        if (in_array($user->role, ['admin', 'procurement', 'auditor'], true)) {
            return true;
        }

        if ($user->role === 'distributor') {
            return $distributor->owner_user_id === $user->id;
        }

        return false;
    }
}
