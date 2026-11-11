<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Publisher for architectural boundary domain events through OutboxBus (DR4-7d).
 *
 * Emits transactional events:
 * - core.boundary.violation_detected.v1
 * - core.boundary.waiver_granted.v1
 * - core.boundary.waiver_expired.v1
 * - core.boundary.status_transitioned.v1
 */
final class BoundaryOutboxPublisher
{
    public function __construct(
        private readonly ?OutboxBusInterface $outboxBus = null,
        private readonly ?ClockInterface $clock = null,
    ) {}

    /**
     * Publish violation detected event.
     */
    public function publishViolationDetected(CoreBoundaryViolation $violation): ?object
    {
        return $this->dispatchEvent(
            'core.boundary.violation_detected.v1',
            array_merge($this->baseContext($violation), [
                'category' => $violation->category,
                'file_path' => $violation->file_path,
                'line_number' => $violation->line_number,
                'signature' => $violation->signature,
                'detected_at' => $this->nowIso(),
            ]),
            "boundary:detected:{$violation->id}",
            $violation,
            'BoundariesScanner'
        );
    }

    /**
     * Publish waiver granted event.
     */
    public function publishWaiverGranted(CoreBoundaryViolation $violation): ?object
    {
        return $this->dispatchEvent(
            'core.boundary.waiver_granted.v1',
            array_merge($this->baseContext($violation), [
                'adr_anchor' => $violation->adr_anchor,
                'waiver_reason' => $violation->waiver_reason,
                'waived_by' => $violation->waived_by,
                'waived_at' => $violation->waived_at?->toIso8601String(),
                'expires_at' => $violation->expires_at?->toIso8601String(),
                'days_valid' => $violation->waived_at && $violation->expires_at
                    ? (int) $violation->waived_at->diffInDays($violation->expires_at)
                    : 90,
            ]),
            "boundary:waived:{$violation->id}",
            $violation,
            'WaiverController'
        );
    }

    /**
     * Publish waiver expired event.
     */
    public function publishWaiverExpired(CoreBoundaryViolation $violation): ?object
    {
        return $this->dispatchEvent(
            'core.boundary.waiver_expired.v1',
            array_merge($this->baseContext($violation), [
                'adr_anchor' => $violation->adr_anchor,
                'expired_at' => $this->nowIso(),
                'previous_expiration' => $violation->expires_at?->toIso8601String(),
            ]),
            "boundary:expired:{$violation->id}",
            $violation,
            'ExpireWaiversAction'
        );
    }

    /**
     * Publish status transition event.
     */
    public function publishStatusTransitioned(
        CoreBoundaryViolation $violation,
        string $fromStatus,
        string $toStatus,
        string $actor,
        string $reason
    ): ?object {
        return $this->dispatchEvent(
            'core.boundary.status_transitioned.v1',
            [
                'violation_id' => $violation->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor' => $actor,
                'reason' => $reason,
                'occurred_at' => $this->nowIso(),
            ],
            "boundary:transition:{$violation->id}:{$fromStatus}-{$toStatus}",
            $violation,
            'BoundaryViolationStateMachine'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseContext(CoreBoundaryViolation $violation): array
    {
        return [
            'violation_id' => $violation->id,
            'rule_id' => $violation->rule_id,
            'client_module' => $violation->client_module,
            'owner_module' => $violation->owner_module,
        ];
    }

    private function nowIso(): string
    {
        return ($this->clock ?? app(ClockInterface::class))->now()->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(
        string $topic,
        array $payload,
        string $idempotencyKey,
        CoreBoundaryViolation $violation,
        string $source
    ): ?object {
        $bus = $this->outboxBus;
        if ($bus === null) {
            try {
                $bus = app(OutboxBusInterface::class);
            } catch (\Throwable) {
                return null;
            }
        }

        return $bus->record(
            $topic,
            $payload,
            $idempotencyKey,
            $violation,
            ['source' => $source, 'version' => '1.0']
        );
    }
}
