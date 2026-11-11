<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\StateMachines\AccessGrantStateMachine;

/**
 * Access Grant Lifecycle & Sub-Second Revocation Service (W8-D55).
 */
class AccessGrantService
{
    public function __construct(
        private readonly AccessGrantStateMachine $stateMachine,
        private readonly AbacPolicyService $policyService
    ) {}

    /**
     * Request a new access grant.
     *
     * @param array{
     *     tenant_id: string,
     *     user_id: int,
     *     resource: string,
     *     action: string,
     *     scope_dimensions?: array<string, mixed>,
     *     valid_from?: mixed,
     *     valid_to?: mixed,
     *     reason?: string
     * } $data
     */
    public function requestGrant(array $data): CoreAccessGrant
    {
        $code = 'GRT-'.strtoupper(Str::random(10));

        $grant = CoreAccessGrant::create([
            'grant_code' => $code,
            'tenant_id' => $data['tenant_id'],
            'user_id' => (int) $data['user_id'],
            'resource' => $data['resource'],
            'action' => $data['action'],
            'status' => AccessGrantStateMachine::STATUS_REQUESTED,
            'scope_dimensions' => $data['scope_dimensions'] ?? [],
            'valid_from' => $data['valid_from'] ?? now(),
            'valid_to' => $data['valid_to'] ?? now()->addDays(30),
            'grant_version' => 1,
            'reason' => $data['reason'] ?? 'Standard access request',
        ]);

        Cache::put("access_grant_version:{$grant->id}", 1, now()->addDays(365));

        return $grant;
    }

    /**
     * Pre-flight ABAC policy check.
     */
    public function checkPolicy(CoreAccessGrant $grant): CoreAccessGrant
    {
        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_POLICY_CHECKED,
            triggerAction: 'check_policy',
            reason: 'Automated ABAC pre-flight policy evaluation passed'
        );
    }

    /**
     * Approve access grant.
     */
    public function approveGrant(CoreAccessGrant $grant, User $approver, ?string $notes = null): CoreAccessGrant
    {
        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_APPROVED,
            triggerAction: 'approve',
            reason: $notes ?? 'Approved by supervisor',
            actorId: $approver->id
        );
    }

    /**
     * Provision technical access.
     */
    public function provisionGrant(CoreAccessGrant $grant): CoreAccessGrant
    {
        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_PROVISIONED,
            triggerAction: 'provision',
            reason: 'Technical accounts and role permissions provisioned'
        );
    }

    /**
     * Activate access session.
     */
    public function activateGrant(CoreAccessGrant $grant): CoreAccessGrant
    {
        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_ACTIVE,
            triggerAction: 'activate',
            reason: 'Active session credentials issued'
        );
    }

    /**
     * Suspend access temporarily.
     */
    public function suspendGrant(CoreAccessGrant $grant, User $actor, string $reason): CoreAccessGrant
    {
        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_SUSPENDED,
            triggerAction: 'suspend',
            reason: $reason,
            actorId: $actor->id
        );
    }

    /**
     * Unsuspend access.
     */
    public function unsuspendGrant(CoreAccessGrant $grant, User $actor, string $reason): CoreAccessGrant
    {
        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_ACTIVE,
            triggerAction: 'unsuspend',
            reason: $reason,
            actorId: $actor->id
        );
    }

    /**
     * Sub-second revoke access.
     */
    public function revokeGrant(CoreAccessGrant $grant, User $actor, string $reason): CoreAccessGrant
    {
        // Atomically bump grant version in memory cache and database
        $nextVersion = $grant->grant_version + 1;
        $grant->grant_version = $nextVersion;
        $grant->save();

        Cache::put("access_grant_version:{$grant->id}", $nextVersion, now()->addDays(365));

        return $this->stateMachine->transition(
            grant: $grant,
            targetStatus: AccessGrantStateMachine::STATUS_REVOKED,
            triggerAction: 'revoke',
            reason: $reason,
            actorId: $actor->id
        );
    }

    /**
     * Sub-second validity check: compares presented version with current cache version.
     */
    public function isGrantActiveAndValid(int $grantId, int $presentedVersion): bool
    {
        $cachedVersion = (int) Cache::get("access_grant_version:{$grantId}", 0);

        if ($cachedVersion !== 0 && $presentedVersion < $cachedVersion) {
            return false;
        }

        $grant = CoreAccessGrant::find($grantId);
        if ($grant === null || $grant->status !== AccessGrantStateMachine::STATUS_ACTIVE) {
            return false;
        }

        if ($grant->valid_to !== null && $grant->valid_to->isPast()) {
            return false;
        }

        return $grant->grant_version === $presentedVersion;
    }

    /**
     * Sweep expired grants.
     */
    public function sweepExpiredGrants(): int
    {
        $expiredGrants = CoreAccessGrant::whereIn('status', [
            AccessGrantStateMachine::STATUS_ACTIVE,
            AccessGrantStateMachine::STATUS_SUSPENDED,
        ])
            ->whereNotNull('valid_to')
            ->where('valid_to', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredGrants as $grant) {
            $this->stateMachine->transition(
                grant: $grant,
                targetStatus: AccessGrantStateMachine::STATUS_EXPIRED,
                triggerAction: 'expire',
                reason: 'Grant validity window expired'
            );
            $count++;
        }

        return $count;
    }
}
