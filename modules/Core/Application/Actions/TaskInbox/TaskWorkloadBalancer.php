<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service penyeimbang beban kerja (Workload Balancer) untuk alokasi tugas cerdas.
 */
class TaskWorkloadBalancer
{
    /**
     * Memilih pengguna terbaik dalam sebuah peran yang memiliki antrean tugas aktif paling sedikit.
     *
     * @param  string  $role  Role target (misal: 'mechanic', 'supervisor', 'finance_officer')
     * @return User|null Pengguna dengan beban tugas paling ringan
     */
    public function findOptimalAssignee(string $role): ?User
    {
        $candidates = User::query()
            ->where('role', $role)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $activeCounts = CoreTask::query()
            ->whereIn('assignee_user_id', $candidates->pluck('id'))
            ->whereIn('status', ['pending', 'in_progress'])
            ->selectRaw('assignee_user_id, count(*) as active_count')
            ->groupBy('assignee_user_id')
            ->pluck('active_count', 'assignee_user_id')
            ->toArray();

        $leastLoadedUser = null;
        $minLoad = PHP_INT_MAX;

        foreach ($candidates as $user) {
            $load = $activeCounts[$user->id] ?? 0;
            if ($load < $minLoad) {
                $minLoad = $load;
                $leastLoadedUser = $user;
            }
        }

        return $leastLoadedUser;
    }
}
