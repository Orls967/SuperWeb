<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\PlatformNotification;

class NotificationService
{
    /**
     * Send a notification to a user.
     */
    public function send(
        int $userId,
        string $type,
        string $title,
        string $body,
        string $icon = 'info',
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?array $meta = null,
    ): PlatformNotification {
        return PlatformNotification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'icon' => $icon,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'meta' => $meta,
        ]);
    }

    /**
     * Get unread count for a user.
     */
    public function unreadCount(int $userId): int
    {
        return PlatformNotification::forUser($userId)->unread()->count();
    }

    /**
     * Get recent notifications for a user.
     */
    public function recent(int $userId, int $limit = 10): Collection
    {
        return PlatformNotification::forUser($userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $notificationId): void
    {
        PlatformNotification::where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all unread notifications as read for a user.
     */
    public function markAllRead(int $userId): int
    {
        return PlatformNotification::forUser($userId)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
