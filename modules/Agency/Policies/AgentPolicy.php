<?php

declare(strict_types=1);

namespace Modules\Agency\Policies;

use App\Models\User;
use Modules\Agency\Domain\Models\Agent;

class AgentPolicy
{
    public function view(User $user, Agent $agent): bool
    {
        if (in_array($user->role, ['admin', 'procurement', 'auditor'], true)) {
            return true;
        }

        if ($user->role === 'agent') {
            return $agent->owner_user_id === $user->id;
        }

        return false;
    }
}
