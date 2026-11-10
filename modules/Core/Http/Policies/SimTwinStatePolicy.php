<?php

declare(strict_types=1);

namespace Modules\Core\Http\Policies;

use App\Models\User;
use Modules\Core\Domain\Models\SimTwinState;

class SimTwinStatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sim_operator', 'superadmin']);
    }

    public function view(User $user, SimTwinState $twin): bool
    {
        return $user->hasAnyRole(['admin', 'sim_operator', 'superadmin']);
    }
}
