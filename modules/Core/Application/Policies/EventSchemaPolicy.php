<?php

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Kebijakan Hak Akses Registrasi & Tata Kelola Skema (EventSchemaPolicy)
 *
 * Mengontrol otorisasi perubahan dan registrasi skema event di Universal Event Spine.
 */
class EventSchemaPolicy
{
    use HandlesAuthorization;

    /**
     * Memeriksa apakah pengguna berhak melihat daftar skema event terdaftar.
     */
    public function viewSchemas(User $user): bool
    {
        return $this->hasSchemaRole($user, ['admin', 'superadmin', 'spine_operator', 'developer', 'auditor']);
    }

    /**
     * Memeriksa apakah pengguna berhak mendaftarkan skema baru yang aditif (A6).
     */
    public function registerSchema(User $user): bool
    {
        return $this->hasSchemaRole($user, ['admin', 'superadmin', 'spine_operator', 'developer']);
    }

    /**
     * Memeriksa apakah pengguna berhak menandai skema lama sebagai terdepresiasi.
     */
    public function deprecateSchema(User $user): bool
    {
        return $this->hasSchemaRole($user, ['admin', 'superadmin', 'lead_architect']);
    }

    /**
     * @param  array<int, string>  $allowedRoles
     */
    protected function hasSchemaRole(User $user, array $allowedRoles): bool
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

