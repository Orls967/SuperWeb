<?php

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Kebijakan Hak Akses Dead-Letter Queue (SpineDlqPolicy)
 *
 * Mengontrol otorisasi tindakan terhadap antrean DLQ dan pesan beracun:
 * melihat rekaman, replay batch, pembuangan (discard), dan rilis dari karantina.
 */
class SpineDlqPolicy
{
    use HandlesAuthorization;

    /**
     * Memeriksa apakah pengguna berhak melihat daftar antrean DLQ.
     */
    public function viewDlq(User $user): bool
    {
        return $this->hasDlqRole($user, ['admin', 'superadmin', 'spine_operator', 'developer', 'auditor']);
    }

    /**
     * Memeriksa apakah pengguna berhak melakukan replay DLQ standar (kuantitas <= 20).
     */
    public function replay(User $user): bool
    {
        return $this->hasDlqRole($user, ['admin', 'superadmin', 'spine_operator']);
    }

    /**
     * Memeriksa apakah pengguna berhak melakukan pembuangan (discard) pesan ber-alasan.
     */
    public function discard(User $user): bool
    {
        return $this->hasDlqRole($user, ['admin', 'superadmin', 'spine_operator']);
    }

    /**
     * Memeriksa apakah pengguna berhak melepas atau memodifikasi pesan dalam karantina poison.
     */
    public function managePoison(User $user): bool
    {
        return $this->hasDlqRole($user, ['admin', 'superadmin', 'lead_architect']);
    }

    /**
     * @param  array<int, string>  $allowedRoles
     */
    protected function hasDlqRole(User $user, array $allowedRoles): bool
    {
        $role = $user->role ?? null;
        if ($role && in_array($role, $allowedRoles, true)) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($allowedRoles);
        }

        return false;
    }
}

