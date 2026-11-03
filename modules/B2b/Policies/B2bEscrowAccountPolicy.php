<?php

declare(strict_types=1);

namespace Modules\B2b\Policies;

use App\Models\User;
use Modules\B2b\Domain\Models\B2bEscrowAccount;

class B2bEscrowAccountPolicy
{
    public function release(User $user, ?B2bEscrowAccount $escrow = null): bool
    {
        return in_array($user->role, ['admin', 'procurement'], true);
    }
}
