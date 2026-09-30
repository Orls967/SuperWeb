<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\ActivityLog;

class ActivityLogger
{
    /**
     * Log an activity.
     */
    public function log(
        string $module,
        string $event,
        string $description,
        ?int $userId = null,
        ?Model $subject = null,
        ?array $properties = null,
    ): ActivityLog {
        return ActivityLog::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'module' => $module,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
        ]);
    }

    /**
     * Get recent activities for a user (across all modules).
     */
    public function recentForUser(int $userId, int $limit = 20): Collection
    {
        return ActivityLog::forUser($userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities across all users (admin view).
     */
    public function recentAll(int $limit = 50): Collection
    {
        return ActivityLog::with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities for a module.
     */
    public function recentForModule(string $module, int $limit = 20): Collection
    {
        return ActivityLog::forModule($module)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
