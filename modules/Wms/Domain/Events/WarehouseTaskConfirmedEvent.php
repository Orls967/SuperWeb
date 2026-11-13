<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Events;

/**
 * Event domain: warehouse.task.confirmed.v1
 */
final readonly class WarehouseTaskConfirmedEvent
{
    public const string TOPIC = 'warehouse.task.confirmed.v1';

    public function __construct(
        public int $taskId,
        public int $warehouseId,
        public string $kind,
        public ?int $actorUserId,
        public string $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'event' => self::TOPIC,
            'task_id' => $this->taskId,
            'warehouse_id' => $this->warehouseId,
            'kind' => $this->kind,
            'actor_user_id' => $this->actorUserId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
