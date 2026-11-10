<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Service eskalasi otomatis tugas yang melampaui tenggat waktu (due date).
 */
class TaskEscalationService
{
    public function __construct(
        protected TaskInboxService $taskInboxService,
    ) {}

    /**
     * Memindai dan mengeksekusi eskalasi terhadap seluruh tugas yang overdue.
     *
     * @return int Jumlah tugas yang dieskalasi
     */
    public function escalateOverdueTasks(): int
    {
        $overdueTasks = CoreTask::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        $count = 0;
        foreach ($overdueTasks as $task) {
            $this->taskInboxService->escalateTask($task, 'Otomatis dieskalasi oleh TaskEscalationService karena melewati tenggat waktu.');
            $count++;
        }

        return $count;
    }
}
