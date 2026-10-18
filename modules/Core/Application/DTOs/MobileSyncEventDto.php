<?php

declare(strict_types=1);

namespace Modules\Core\Application\Dtos;

use DateTimeInterface;

final readonly class MobileSyncEventDto
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $idempotencyKey,
        public string $deviceUuid,
        public string $roleScope,
        public string $eventType,
        public array $payload,
        public DateTimeInterface $occurredOfflineAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $occurred = $data['occurred_offline_at'] ?? now();
        if (is_string($occurred)) {
            $occurred = new \DateTimeImmutable($occurred);
        }

        $eventId = (string) ($data['event_id'] ?? ($data['reference_id'] ?? 'init'));
        $deviceUuid = (string) ($data['device_uuid'] ?? 'dev');

        return new self(
            idempotencyKey: (string) ($data['idempotency_key'] ?? sprintf('mob:sync:%s:%s', $deviceUuid, $eventId)),
            deviceUuid: $deviceUuid,
            roleScope: (string) ($data['role_scope'] ?? 'operator'),
            eventType: (string) ($data['event_type'] ?? 'GENERAL_ACTION'),
            payload: (array) ($data['payload'] ?? []),
            occurredOfflineAt: $occurred instanceof DateTimeInterface ? $occurred : now(),
        );
    }
}
