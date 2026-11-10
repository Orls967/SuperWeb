<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Service penjadwalan reminder otomatis menjelang batas tenggat tugas.
 */
class TaskReminderScheduler
{
    /**
     * Mengambil daftar tugas yang membutuhkan peringatan segera (due dalam kurun waktu jam tertentu).
     *
     * @return array<int, array{task: CoreTask, hours_left: float, severity: string}>
     */
    public function getPendingReminders(int $lookaheadHours = 24): array
    {
        $now = now();
        $cutoff = now()->addHours($lookaheadHours);

        $tasks = CoreTask::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '>', $now)
            ->where('due_at', '<=', $cutoff)
            ->get();

        $reminders = [];
        foreach ($tasks as $task) {
            $hoursLeft = round(($task->due_at->getTimestamp() - $now->getTimestamp()) / 3600.0, 1);
            $severity = ($hoursLeft <= 4) ? 'critical' : (($hoursLeft <= 12) ? 'warning' : 'info');

            $reminders[] = [
                'task' => $task,
                'hours_left' => $hoursLeft,
                'severity' => $severity,
            ];
        }

        return $reminders;
    }
}
