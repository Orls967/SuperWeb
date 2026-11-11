<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Modules\Core\Application\Boundaries\BoundaryOutboxPublisher;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\Models\CoreBoundaryViolationTransition;

/**
 * State Machine for BoundaryViolation lifecycle (7 statuses) (DR4-1, W8-D43).
 * Enforces strict transitions, guard invariants, and append-only transition logging.
 */
final class BoundaryViolationStateMachine
{
    /** @var array<string, list<string>> valid transitions: from => list of allowed to */
    public const VALID_TRANSITIONS = [
        ViolationStatus::DETECTED->value => [
            ViolationStatus::TRIAGED->value,
        ],
        ViolationStatus::TRIAGED->value => [
            ViolationStatus::CONTRACT_DESIGNED->value,
            ViolationStatus::WAIVED->value,
        ],
        ViolationStatus::CONTRACT_DESIGNED->value => [
            ViolationStatus::MIGRATING->value,
        ],
        ViolationStatus::MIGRATING->value => [
            ViolationStatus::VERIFIED->value,
        ],
        ViolationStatus::VERIFIED->value => [
            ViolationStatus::CLOSED->value,
        ],
        ViolationStatus::WAIVED->value => [
            ViolationStatus::TRIAGED->value,
        ],
        ViolationStatus::CLOSED->value => [
            // Terminal status: no transitions allowed out of closed
        ],
    ];

    public function __construct(
        private readonly ?BoundaryOutboxPublisher $outboxPublisher = null,
    ) {}

    /**
     * Check if a transition is structurally allowed.
     */
    public static function canTransition(ViolationStatus $from, ViolationStatus $to): bool
    {
        $allowed = self::VALID_TRANSITIONS[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    /**
     * Get list of allowed target statuses from a current status.
     *
     * @return list<string>
     */
    public static function allowedTargets(ViolationStatus $from): array
    {
        return self::VALID_TRANSITIONS[$from->value] ?? [];
    }

    /**
     * Apply a transition with guard checks and record transition fact.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        CoreBoundaryViolation $violation,
        ViolationStatus $targetStatus,
        string $actorId,
        string $reason,
        array $metadata = [],
    ): CoreBoundaryViolation {
        $currentStatus = $violation->status instanceof ViolationStatus
            ? $violation->status
            : ViolationStatus::from((string) $violation->status);

        if (! self::canTransition($currentStatus, $targetStatus)) {
            throw new DomainException("BOUND_SM_INVALID_TRANSITION: Ilegal transition from '{$currentStatus->value}' to '{$targetStatus->value}' for violation #{$violation->id}.");
        }

        $this->evaluateGuards($violation, $currentStatus, $targetStatus, $metadata);

        // Update violation record
        $violation->status = $targetStatus;

        if (isset($metadata['contract_name'])) {
            $violation->contract_name = (string) $metadata['contract_name'];
        }
        if (isset($metadata['adr_anchor'])) {
            $violation->adr_anchor = (string) $metadata['adr_anchor'];
        }
        if (isset($metadata['waiver_reason'])) {
            $violation->waiver_reason = (string) $metadata['waiver_reason'];
        }
        if (isset($metadata['waived_by'])) {
            $violation->waived_by = (string) $metadata['waived_by'];
        }
        if (isset($metadata['waived_at'])) {
            $violation->waived_at = $metadata['waived_at'];
        }
        if (isset($metadata['expires_at'])) {
            $violation->expires_at = $metadata['expires_at'];
        }

        $violation->save();

        // Record append-only transition history
        $transitionRecord = CoreBoundaryViolationTransition::create([
            'violation_id' => $violation->id,
            'from_status' => $currentStatus,
            'to_status' => $targetStatus,
            'actor' => $actorId,
            'reason' => $reason,
            'commit_sha' => $metadata['commit_sha'] ?? null,
            'metadata' => $metadata !== [] ? $metadata : null,
            'occurred_at' => now(),
        ]);

        // Publish outbox event
        $publisher = $this->resolvePublisher();
        $publisher?->publishStatusTransitioned(
            $violation,
            $currentStatus->value,
            $targetStatus->value,
            $actorId,
            $reason
        );

        return $violation;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function evaluateGuards(
        CoreBoundaryViolation $violation,
        ViolationStatus $from,
        ViolationStatus $to,
        array $metadata,
    ): void {
        // Guard: detected -> triaged (category and owner must be present)
        if ($from === ViolationStatus::DETECTED && $to === ViolationStatus::TRIAGED) {
            if (empty($violation->category) || empty($violation->owner_module)) {
                throw new DomainException('Guard failed: Category and owner_module must be assigned before triaging.');
            }
        }

        // Guard: triaged -> contract_designed (contract name, owner, and ADR anchor)
        if ($from === ViolationStatus::TRIAGED && $to === ViolationStatus::CONTRACT_DESIGNED) {
            $contractName = $metadata['contract_name'] ?? $violation->contract_name;
            $adrAnchor = $metadata['adr_anchor'] ?? $violation->adr_anchor;

            if (empty($contractName) || empty($adrAnchor)) {
                throw new DomainException('Guard failed: contract_name and adr_anchor must be recorded to move to contract_designed.');
            }
        }

        // Guard: contract_designed -> migrating (requires active migration reason)
        if ($from === ViolationStatus::CONTRACT_DESIGNED && $to === ViolationStatus::MIGRATING) {
            if (empty($violation->contract_name) && empty($metadata['contract_name'])) {
                throw new DomainException('Guard failed: Contract interface must be bound before migrating call sites.');
            }
        }

        // Guard: triaged -> waived (anchor DECISIONS, reason, approver, expiry <= 90 days)
        if ($from === ViolationStatus::TRIAGED && $to === ViolationStatus::WAIVED) {
            $anchor = $metadata['adr_anchor'] ?? $violation->adr_anchor;
            $reason = $metadata['waiver_reason'] ?? $violation->waiver_reason;
            $expiresAt = $metadata['expires_at'] ?? $violation->expires_at;

            if (empty($anchor)) {
                throw new DomainException('Guard failed: Waiver requires valid DECISIONS.md adr_anchor.');
            }
            if (empty($reason)) {
                throw new DomainException('Guard failed: Waiver requires documented technical reason.');
            }
            if ($expiresAt === null) {
                throw new DomainException('Guard failed: Waiver requires expires_at deadline.');
            }

            $expires = $expiresAt instanceof CarbonInterface
                ? $expiresAt
                : Carbon::parse($expiresAt);

            $days = (int) now()->diffInDays($expires, false);
            if ($days <= 0) {
                throw new DomainException('Guard failed: Waiver expires_at cannot be in the past.');
            }
            if ($days > 90) {
                throw new DomainException("Guard failed: Waiver expires_at cannot exceed 90 days (requested: {$days} days).");
            }
        }

        // Guard: verified -> closed (commit_sha must be recorded)
        if ($from === ViolationStatus::VERIFIED && $to === ViolationStatus::CLOSED) {
            $commit = $metadata['commit_sha'] ?? null;
            if (empty($commit)) {
                throw new DomainException('Guard failed: Transition to closed requires commit_sha proving clean arch:scan.');
            }
        }
    }

    private function resolvePublisher(): ?BoundaryOutboxPublisher
    {
        if ($this->outboxPublisher !== null) {
            return $this->outboxPublisher;
        }

        try {
            return app(BoundaryOutboxPublisher::class);
        } catch (\Throwable) {
            return null;
        }
    }
}
