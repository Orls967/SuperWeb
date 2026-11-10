<?php

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Kebijakan Hak Akses Operasional Event Spine (EventSpinePolicy)
 *
 * Mengatur hak akses pengguna terhadap konsol pemantauan dan operasi inti Universal Event Spine.
 */
class EventSpinePolicy
{
    use HandlesAuthorization;

    /**
     * Memeriksa apakah pengguna berhak melihat dasbor Event Spine.
     */
    public function viewDashboard(User $user): bool
    {
        return $this->hasSpineRole($user, ['admin', 'superadmin', 'spine_operator', 'developer']);
    }

    /**
     * Memeriksa apakah pengguna berhak memicu audit integritas dual-source.
     */
    public function runAudit(User $user): bool
    {
        return $this->hasSpineRole($user, ['admin', 'superadmin', 'spine_operator', 'auditor']);
    }

    /**
     * Memeriksa apakah pengguna berhak melihat daftar grup konsumen dan metrik lag.
     */
    public function viewConsumers(User $user): bool
    {
        return $this->hasSpineRole($user, ['admin', 'superadmin', 'spine_operator', 'developer']);
    }

    /**
     * Memeriksa apakah pengguna berhak melakukan intervensi atau restart grup konsumen.
     */
    public function manageConsumers(User $user): bool
    {
        return $this->hasSpineRole($user, ['admin', 'superadmin', 'spine_operator']);
    }

    /**
     * Memeriksa apakah pengguna memiliki salah satu role yang diizinkan.
     *
     * @param  array<int, string>  $allowedRoles
     */
    protected function hasSpineRole(User $user, array $allowedRoles): bool
    {
        $role = $user->role ?? null;
        if ($role && in_array($role, $allowedRoles, true)) {
            return true;
        }

        // Cek jika pengguna memiliki method hasRole() atau hasAnyRole()
        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($allowedRoles);
        }

        return false;
    }
}
