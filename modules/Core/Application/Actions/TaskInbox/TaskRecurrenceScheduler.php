<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use DateTimeInterface;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service pembuat tugas berulang periodik (Recurring Task Scheduler).
 */
class TaskRecurrenceScheduler
{
    public function __construct(
        protected TaskInboxService $inboxService,
    ) {}

    /**
     * Membentuk instansi tugas siklik berikutnya berdasarkan pola pengulangan ('daily', 'weekly', 'monthly', 'quarterly').
     */
    public function scheduleNextOccurrence(CoreTask $parentTask, string $recurrencePattern, ?DateTimeInterface $baseDate = null): CoreTask
    {
        $base = $baseDate ? \DateTimeImmutable::createFromInterface($baseDate) : new \DateTimeImmutable;

        $nextDue = match ($recurrencePattern) {
            'daily' => $base->modify('+1 day'),
            'weekly' => $base->modify('+1 week'),
            'monthly' => $base->modify('+1 month'),
            'quarterly' => $base->modify('+3 months'),
            default => $base->modify('+1 day'),
        };

        return $this->inboxService->createTask([
            'title' => '[Rutin '.ucfirst($recurrencePattern).'] '.$parentTask->title,
            'description' => $parentTask->description,
            'module' => $parentTask->module,
            'source_entity_type' => $parentTask->source_entity_type,
            'source_entity_id' => $parentTask->source_entity_id,
            'assignee_user_id' => $parentTask->assignee_user_id,
            'assignee_role' => $parentTask->assignee_role,
            'priority' => $parentTask->priority,
            'due_at' => $nextDue,
            'action_url' => $parentTask->action_url,
            'payload' => array_merge($parentTask->payload ?? [], [
                'recurring_pattern' => $recurrencePattern,
                'parent_task_id' => $parentTask->id,
            ]),
        ]);
    }
}
