<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Service penyiar notifikasi tugas (Task Notification Broadcaster).
 */
class TaskNotificationBroadcaster
{
    public function __construct(
        protected ?NotificationService $notificationService = null,
    ) {}

    protected function getNotificationService(): ?NotificationService
    {
        return $this->notificationService ?? (app()->bound(NotificationService::class) ? app(NotificationService::class) : null);
    }

    /**
     * Mengirimkan notifikasi penugasan tugas baru kepada assignee.
     */
    public function broadcastTaskAssigned(CoreTask $task): bool
    {
        if (empty($task->assignee_user_id)) {
            return false;
        }

        $service = $this->getNotificationService();
        if ($service !== null && method_exists($service, 'send')) {
            try {
                $service->send(
                    userId: $task->assignee_user_id,
                    title: 'Tugas Baru: '.$task->title,
                    message: (string) ($task->description ?? 'Anda menerima tugas baru di Kotak Masuk Tugas Saya.'),
                    type: 'task_assigned',
                    actionUrl: $task->action_url ?? ('/inbox/'.$task->id)
                );

                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }
}
