<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use DomainException;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;

/**
 * 8-Status Access Grant State Machine (W8-D55).
 *
 * Statuses:
 * - requested
 * - policy_checked
 * - approved
 * - provisioned
 * - active
 * - suspended
 * - revoked (terminal)
 * - expired (terminal)
 */
class AccessGrantStateMachine
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_POLICY_CHECKED = 'policy_checked';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PROVISIONED = 'provisioned';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const ALL_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_POLICY_CHECKED,
        self::STATUS_APPROVED,
        self::STATUS_PROVISIONED,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_REVOKED,
        self::STATUS_EXPIRED,
    ];

    /**
     * Allowed transition mapping: [from_status => [to_status => trigger_action]].
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_REQUESTED => [
            self::STATUS_POLICY_CHECKED => 'check_policy',
            self::STATUS_REVOKED => 'reject',
        ],
        self::STATUS_POLICY_CHECKED => [
            self::STATUS_APPROVED => 'approve',
            self::STATUS_REVOKED => 'reject',
        ],
        self::STATUS_APPROVED => [
            self::STATUS_PROVISIONED => 'provision',
            self::STATUS_REVOKED => 'revoke',
        ],
        self::STATUS_PROVISIONED => [
            self::STATUS_ACTIVE => 'activate',
            self::STATUS_REVOKED => 'revoke',
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_SUSPENDED => 'suspend',
            self::STATUS_REVOKED => 'revoke',
            self::STATUS_EXPIRED => 'expire',
        ],
        self::STATUS_SUSPENDED => [
            self::STATUS_ACTIVE => 'unsuspend',
            self::STATUS_REVOKED => 'revoke',
            self::STATUS_EXPIRED => 'expire',
        ],
        self::STATUS_REVOKED => [],
        self::STATUS_EXPIRED => [],
    ];

    /**
     * Transition grant to new status with guard evaluation and transition recording.
     */
    public function transition(
        CoreAccessGrant $grant,
        string $targetStatus,
        string $triggerAction,
        ?string $reason = null,
        ?int $actorId = null
    ): CoreAccessGrant {
        $fromStatus = $grant->status;

        if (! in_array($targetStatus, self::ALL_STATUSES, true)) {
            throw new DomainException("INVALID_STATUS: Status target '{$targetStatus}' tidak dikenal.");
        }

        $allowedTo = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        if (! isset($allowedTo[$targetStatus])) {
            throw new DomainException(
                "ILLEGAL_TRANSITION: Transisi dari '{$fromStatus}' ke '{$targetStatus}' via '{$triggerAction}' dilarang."
            );
        }

        // Apply guards
        $this->evaluateGuards($grant, $fromStatus, $targetStatus, $triggerAction, $reason);

        // Update grant state
        $grant->status = $targetStatus;

        if ($targetStatus === self::STATUS_REVOKED) {
            $grant->revoked_at = now();
        }

        $grant->save();

        // Record transition append-only
        CoreAccessGrantTransition::create([
            'grant_id' => $grant->id,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'trigger_action' => $triggerAction,
            'reason' => $reason,
            'actor_id' => $actorId,
            'transitioned_at' => now(),
        ]);

        return $grant;
    }

    private function evaluateGuards(
        CoreAccessGrant $grant,
        string $from,
        string $to,
        string $action,
        ?string $reason
    ): void {
        if ($to === self::STATUS_REVOKED || $to === self::STATUS_SUSPENDED) {
            if ($reason === null || trim($reason) === '') {
                throw new DomainException("GUARD_FAILED: Alasan wajib diisi saat transisi ke '{$to}'.");
            }
        }

        if ($to === self::STATUS_ACTIVE && $grant->valid_to !== null && $grant->valid_to->isPast()) {
            throw new DomainException('GUARD_FAILED: Tidak dapat mengaktifkan grant yang masa berlakunya telah kedaluwarsa.');
        }
    }
}
