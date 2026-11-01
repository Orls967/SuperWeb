<?php

declare(strict_types=1);

namespace Modules\Agri\Policies;

use App\Models\User;
use Modules\Agri\Domain\Models\AgriContract;

class AgriContractPolicy
{
    public function settle(User $user, ?AgriContract $contract = null): bool
    {
        return in_array($user->role, ['admin', 'procurement'], true);
    }
}
