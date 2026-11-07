<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Policies;

use App\Models\User;
use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningPlan;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Kebijakan Otorisasi & Perlindungan Anti-IDOR tingkat Objek Fase 93 (U-5).
 *
 * Mengawal hak akses per-objek untuk:
 * - Perencanaan Tambang (mine_planner, admin)
 * - Papan Dispatch Armada (dispatcher_mine, mine_manager, site_manager, mining_admin, admin)
 * - Telemetri & OEE Alat Berat (mine_manager, admin)
 * - Pengelolaan BBM & Resolusi Anomali (finance_officer, hse_officer, admin)
 * - Portal Isolasi Kontraktor (contractor hanya boleh melihat & menyanggah objek miliknya sendiri)
 */
final class MiningFase93AccessPolicy
{
    public function authorizeMinePlanAccess(?User $user, ?MiningPlan $plan = null): void
    {
        if (! $user || ! $this->hasAnyRole($user, ['mine_planner', 'admin'])) {
            throw new AccessDeniedHttpException('Akses ditolak: hanya perencana tambang (mine_planner) atau admin yang berwenang.');
        }

        if ($plan !== null && (string) $plan->site_id === '') {
            throw new AccessDeniedHttpException('Rencana tambang tidak memiliki afiliasi site yang sah.');
        }
    }

    public function authorizeDispatchAccess(?User $user): void
    {
        if (! $user || ! $this->hasAnyRole($user, ['dispatcher_mine', 'mine_manager', 'site_manager', 'mining_admin', 'admin'])) {
            throw new AccessDeniedHttpException('Akses ditolak: hanya petugas dispatch tambang (dispatcher_mine) yang berwenang.');
        }
    }

    public function authorizeEquipmentAccess(?User $user): void
    {
        if (! $user || ! $this->hasAnyRole($user, ['mine_manager', 'admin'])) {
            throw new AccessDeniedHttpException('Akses ditolak: hanya manajer tambang (mine_manager) yang berwenang.');
        }
    }

    public function authorizeFuelAccess(?User $user): void
    {
        if (! $user || ! $this->hasAnyRole($user, ['finance_officer', 'hse_officer', 'admin'])) {
            throw new AccessDeniedHttpException('Akses ditolak: hanya petugas keuangan atau K3L yang berwenang mengelola anomali BBM.');
        }
    }

    /**
     * Penegakan Anti-IDOR ketat pada kasus anomali BBM milik kontraktor.
     */
    public function assertContractorOwnsFuelCase(User $user, MiningFuelAnomalyCase $case): void
    {
        if ($this->hasAnyRole($user, ['admin', 'finance_officer'])) {
            return;
        }

        $actorRef = (string) $user->id;
        $caseOwnerRef = (string) ($case->contractor_party_ref ?: $case->contractor_party_id);

        if ($caseOwnerRef === '' || $caseOwnerRef !== $actorRef) {
            abort(403, 'Akses ditolak (Anti-IDOR): Kasus anomali BBM ini milik pihak kontraktor lain.');
        }
    }

    /**
     * Penegakan Anti-IDOR pada dokumen penahanan pembayaran kontraktor.
     */
    public function canViewContractorHold(User $user, MiningContractorPaymentHold $hold): bool
    {
        if ($this->hasAnyRole($user, ['admin', 'finance_officer'])) {
            return true;
        }

        $actorRef = (string) $user->id;
        $holdOwnerRef = (string) ($hold->contractor_party_ref ?: $hold->contractor_party_id);

        return $holdOwnerRef !== '' && $holdOwnerRef === $actorRef;
    }

    /**
     * @param  list<string>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
