<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Application\Actions\TaskInbox\TaskSlaRuleEngine;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service ekspor data audit tugas kotak masuk dan pelaporan performa SLA.
 */
class TaskInboxExportService
{
    public function __construct(
        protected TaskSlaRuleEngine $slaEngine,
    ) {}

    /**
     * Menghasilkan file CSV laporan kepatuhan SLA seluruh tugas aktif dan selesai.
     */
    public function exportTasksCsv(): string
    {
        $tasks = CoreTask::query()->with('histories')->orderBy('created_at', 'desc')->limit(1000)->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Task ID',
            'Title',
            'Module',
            'Status',
            'Priority',
            'Assignee User ID',
            'Assignee Role',
            'Due Date',
            'SLA Status',
            'Escalation Level',
            'Created At',
        ]);

        foreach ($tasks as $task) {
            $slaStatus = $this->slaEngine->evaluateSlaStatus($task);

            fputcsv($output, [
                $task->id,
                $task->title,
                $task->module,
                $task->status,
                $task->priority,
                $task->assignee_user_id ?? 'UNASSIGNED',
                $task->assignee_role ?? '-',
                $task->due_at?->toIso8601String() ?? 'NONE',
                $slaStatus,
                $task->escalation_level,
                $task->created_at->toIso8601String(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }
}
