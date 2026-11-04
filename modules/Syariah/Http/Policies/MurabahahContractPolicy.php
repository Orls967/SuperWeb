<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Policies;

use App\Models\User;
use Modules\Syariah\Domain\Models\MurabahahContract;

final class MurabahahContractPolicy
{
    public function view(User $user, MurabahahContract $contract): bool
    {
        if (in_array($user->role, ['admin', 'syariah_officer', 'dps_reviewer', 'auditor'], true)) {
            return true;
        }

        return $user->role === 'nasabah' && (int) $contract->customer_user_id === (int) $user->id;
    }
}
