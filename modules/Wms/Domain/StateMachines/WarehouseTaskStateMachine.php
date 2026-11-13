<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\StateMachines;

use DomainException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\TaskEvent;

/**
 * State Machine 7-status untuk tugas gudang (D41-1):
 * created -> assigned -> in_progress -> confirmed -> closed (plus exception & cancelled).
 */
final class WarehouseTaskStateMachine
{
    public const string STATUS_CREATED = 'created';

    public const string STATUS_ASSIGNED = 'assigned';

    public const string STATUS_IN_PROGRESS = 'in_progress';

    public const string STATUS_CONFIRMED = 'confirmed';

    public const string STATUS_EXCEPTION = 'exception';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_CLOSED = 'closed';

    /** @var array<string, array<string, string>> */
    private const array TRANSITIONS = [
        self::STATUS_CREATED => [
            'assign' => self::STATUS_ASSIGNED,
            'cancel' => self::STATUS_CANCELLED,
        ],
        self::STATUS_ASSIGNED => [
            'start' => self::STATUS_IN_PROGRESS,
            'cancel' => self::STATUS_CANCELLED,
        ],
        self::STATUS_IN_PROGRESS => [
            'confirm' => self::STATUS_CONFIRMED,
            'flag_exception' => self::STATUS_EXCEPTION,
            'cancel' => self::STATUS_CANCELLED,
        ],
        self::STATUS_EXCEPTION => [
            'retry' => self::STATUS_IN_PROGRESS,
            'resolve' => self::STATUS_CONFIRMED,
            'cancel' => self::STATUS_CANCELLED,
        ],
        self::STATUS_CONFIRMED => [
            'close' => self::STATUS_CLOSED,
        ],
        self::STATUS_CLOSED => [],
        self::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {}

    /**
     * @return list<string>
     */
    public static function allStates(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public function canTransition(string $currentStatus, string $action): bool
    {
        return isset(self::TRANSITIONS[$currentStatus][$action]);
    }

    public function nextState(string $currentStatus, string $action): string
    {
        if (! isset(self::TRANSITIONS[$currentStatus][$action])) {
            throw new DomainException(
                "Transisi tugas gudang tidak diizinkan: dari '{$currentStatus}' dengan aksi '{$action}'."
            );
        }

        return self::TRANSITIONS[$currentStatus][$action];
    }

    /**
     * Mengeksekusi transisi status pada model Task & mencatat event append-only ke wms_task_events.
     *
     * @param  array<string, mixed>  $payload
     */
    public function transition(
        Task $task,
        string $action,
        ?int $actorUserId = null,
        ?string $reason = null,
        array $payload = [],
    ): Task {
        $from = (string) ($task->status ?: self::STATUS_CREATED);
        // Normalisasi legacy status 'open'/'completed' bila ada data lama
        if ($from === 'open') {
            $from = self::STATUS_CREATED;
        }
        $to = $this->nextState($from, $action);

        $now = ($this->clock ?? app(ClockInterface::class))->now();

        $task->status = $to;
        if ($to === self::STATUS_ASSIGNED && $actorUserId !== null && $task->assigned_to === null) {
            $task->assigned_to = $actorUserId;
        }
        if ($to === self::STATUS_IN_PROGRESS && $task->started_at === null) {
            $task->started_at = $now;
        }
        if (in_array($to, [self::STATUS_CONFIRMED, self::STATUS_CLOSED], true) && $task->completed_at === null) {
            $task->completed_at = $now;
        }
        $task->save();

        TaskEvent::create([
            'task_id' => $task->id,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'actor_user_id' => $actorUserId,
            'reason' => $reason,
            'payload_json' => $payload === [] ? null : $payload,
            'created_at' => $now,
        ]);

        return $task;
    }
}
