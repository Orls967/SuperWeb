<?php

declare(strict_types=1);

namespace Modules\Platform\Policies;

use App\Models\User;
use Modules\Platform\Domain\Models\SandboxEnvironment;

class SandboxEnvironmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SandboxEnvironment $sandbox): bool
    {
        return $user->id === $sandbox->owner_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SandboxEnvironment $sandbox): bool
    {
        return $user->id === $sandbox->owner_id;
    }

    public function delete(User $user, SandboxEnvironment $sandbox): bool
    {
        return $user->id === $sandbox->owner_id;
    }
}

