<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use App\Models\User;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service resolusi rantai eskalasi tugas berjenjang (Multi-Tier Escalation Chain Resolver).
 * Level 1: Supervisor Modul
 * Level 2: Manager Departemen
 * Level 3: Vice President / General Manager
 * Level 4: Board of Directors / Chief Executive.
 */
class TaskEscalationChainResolver
{
    private const ESCALATION_TIERS = [
        1 => 'supervisor',
        2 => 'manager',
        3 => 'director',
        4 => 'executive',
    ];

    /**
     * Menyelesaikan target penanggung jawab berikutnya saat tingkat eskalasi dinaikkan.
     *
     * @return array{next_level: int, target_role: string, target_user: User|null}
     */
    public function resolveNextEscalationTarget(CoreTask $task): array
    {
        $currentLevel = (int) $task->escalation_level;
        $nextLevel = min(4, $currentLevel + 1);
        $targetRole = self::ESCALATION_TIERS[$nextLevel] ?? 'admin';

        $targetUser = User::query()->where('role', $targetRole)->first();
        if ($targetUser === null) {
            $targetUser = User::query()->where('role', 'admin')->first();
        }

        return [
            'next_level' => $nextLevel,
            'target_role' => $targetRole,
            'target_user' => $targetUser,
        ];
    }
}
