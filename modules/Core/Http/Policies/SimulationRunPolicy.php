<?php

declare(strict_types=1);

namespace Modules\Core\Http\Policies;

use App\Models\User;
use Modules\Core\Domain\Models\SimRun;

class SimulationRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sim_operator', 'superadmin']);
    }

    public function view(User $user, SimRun $run): bool
    {
        if (! $user->hasAnyRole(['admin', 'sim_operator', 'superadmin'])) {
            return false;
        }

        // Anti-IDOR: sim_operator hanya dapat melihat run miliknya jika created_by terdefinisi
        if ($run->created_by !== null && (int) $run->created_by !== (int) $user->id && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sim_operator', 'superadmin']);
    }

    public function pause(User $user, SimRun $run): bool
    {
        if (! $user->hasAnyRole(['admin', 'sim_operator', 'superadmin'])) {
            return false;
        }

        // Anti-IDOR guard
        if ($run->created_by !== null && (int) $run->created_by !== (int) $user->id && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function resume(User $user, SimRun $run): bool
    {
        if (! $user->hasAnyRole(['admin', 'sim_operator', 'superadmin'])) {
            return false;
        }

        if ($run->created_by !== null && (int) $run->created_by !== (int) $user->id && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }
}
