<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Illuminate\Support\Str;
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
    ) {}

    /**
     * Publish violation detected event.
     */
    public function publishViolationDetected(CoreBoundaryViolation $violation): ?object
    {
        $bus = $this->resolveBus();
        if ($bus === null) {
            return null;
        }

        $payload = [
            'violation_id' => $violation->id,
            'rule_id' => $violation->rule_id,
            'category' => $violation->category,
            'client_module' => $violation->client_module,
            'owner_module' => $violation->owner_module,
            'file_path' => $violation->file_path,
            'line_number' => $violation->line_number,
            'signature' => $violation->signature,
            'detected_at' => now()->toIso8601String(),
        ];

        $idempotencyKey = "boundary:detected:{$violation->id}";

        return $bus->record(
            'core.boundary.violation_detected.v1',
            $payload,
            $idempotencyKey,
            $violation,
            ['source' => 'BoundariesScanner', 'version' => '1.0']
        );
    }

    /**
     * Publish waiver granted event.
     */
    public function publishWaiverGranted(CoreBoundaryViolation $violation): ?object
    {
        $bus = $this->resolveBus();
        if ($bus === null) {
            return null;
        }

        $payload = [
            'violation_id' => $violation->id,
            'rule_id' => $violation->rule_id,
            'client_module' => $violation->client_module,
            'owner_module' => $violation->owner_module,
            'adr_anchor' => $violation->adr_anchor,
            'waiver_reason' => $violation->waiver_reason,
            'waived_by' => $violation->waived_by,
            'waived_at' => $violation->waived_at?->toIso8601String(),
            'expires_at' => $violation->expires_at?->toIso8601String(),
            'days_valid' => $violation->waived_at && $violation->expires_at
                ? (int) $violation->waived_at->diffInDays($violation->expires_at)
                : 90,
        ];

        $idempotencyKey = "boundary:waived:{$violation->id}";

        return $bus->record(
            'core.boundary.waiver_granted.v1',
            $payload,
            $idempotencyKey,
            $violation,
            ['source' => 'WaiverController', 'version' => '1.0']
        );
    }

    /**
     * Publish waiver expired event.
     */
    public function publishWaiverExpired(CoreBoundaryViolation $violation): ?object
    {
        $bus = $this->resolveBus();
        if ($bus === null) {
            return null;
        }

        $payload = [
            'violation_id' => $violation->id,
            'rule_id' => $violation->rule_id,
            'client_module' => $violation->client_module,
            'owner_module' => $violation->owner_module,
            'adr_anchor' => $violation->adr_anchor,
            'expired_at' => now()->toIso8601String(),
            'previous_expiration' => $violation->expires_at?->toIso8601String(),
        ];

        $idempotencyKey = "boundary:expired:{$violation->id}";

        return $bus->record(
            'core.boundary.waiver_expired.v1',
            $payload,
            $idempotencyKey,
            $violation,
            ['source' => 'ExpireWaiversAction', 'version' => '1.0']
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
        $bus = $this->resolveBus();
        if ($bus === null) {
            return null;
        }

        $payload = [
            'violation_id' => $violation->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor' => $actor,
            'reason' => $reason,
            'occurred_at' => now()->toIso8601String(),
        ];

        $idempotencyKey = "boundary:transition:{$violation->id}:{$fromStatus}-{$toStatus}";

        return $bus->record(
            'core.boundary.status_transitioned.v1',
            $payload,
            $idempotencyKey,
            $violation,
            ['source' => 'BoundaryViolationStateMachine', 'version' => '1.0']
        );
    }

    private function resolveBus(): ?OutboxBusInterface
    {
        if ($this->outboxBus !== null) {
            return $this->outboxBus;
        }

        try {
            return app(OutboxBusInterface::class);
        } catch (\Throwable) {
            return null;
        }
    }
}

