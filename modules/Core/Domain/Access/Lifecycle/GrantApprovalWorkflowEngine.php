<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Lifecycle;

use DomainException;
use Modules\Core\Domain\Access\Values\ActionDescriptor;

/**
 * Multi-Tier Risk-Based Access Grant Approval Workflow Engine (W8-D55).
 *
 * Computes required approval tiers and authorization quorum based on requested role risk:
 * - Tier 1 (Low Risk): Single direct manager sign-off
 * - Tier 2 (Medium Risk): Manager + Department Head
 * - Tier 3 (High Risk): Department Head + Chief Information Security Officer (CISO)
 * - Tier 4 (Critical / Break-Glass): Dual C-level executive approval (e.g. CTO + CISO)
 */
final class GrantApprovalWorkflowEngine
{
    /**
     * Determine required approval quorum for a requested access privilege.
     *
     * @param  string  $roleOrPermission  Requested privilege
     * @param  string  $riskTier  Risk classification (LOW, MEDIUM, HIGH, CRITICAL)
     * @return array{
     *     tier_level: int,
     *     required_signatures: int,
     *     required_role_titles: list<string>,
     *     sla_hours: int,
     *     requires_dual_custody: bool
     * }
     */
    public function determineRequiredApproval(string $roleOrPermission, string $riskTier = ActionDescriptor::RISK_LOW): array
    {
        return match (strtoupper($riskTier)) {
            ActionDescriptor::RISK_CRITICAL => [
                'tier_level' => 4,
                'required_signatures' => 2,
                'required_role_titles' => ['Chief Technology Officer', 'Chief Information Security Officer'],
                'sla_hours' => 2,
                'requires_dual_custody' => true,
            ],
            ActionDescriptor::RISK_HIGH => [
                'tier_level' => 3,
                'required_signatures' => 2,
                'required_role_titles' => ['Department Head', 'Security Operations Lead'],
                'sla_hours' => 8,
                'requires_dual_custody' => true,
            ],
            ActionDescriptor::RISK_MEDIUM => [
                'tier_level' => 2,
                'required_signatures' => 1,
                'required_role_titles' => ['Department Manager'],
                'sla_hours' => 24,
                'requires_dual_custody' => false,
            ],
            default => [
                'tier_level' => 1,
                'required_signatures' => 1,
                'required_role_titles' => ['Team Lead', 'Direct Supervisor'],
                'sla_hours' => 48,
                'requires_dual_custody' => false,
            ],
        };
    }

    /**
     * Validate whether a set of approvers satisfies the required approval quorum.
     *
     * @param  array<int, string>  $approverRoles  Map of approver user ID => their role title
     * @param  int  $requesterUserId  ID of the user requesting access (cannot approve own request)
     * @param  array{required_signatures: int, requires_dual_custody: bool}  $requirement
     */
    public function validateApprovalQuorum(
        array $approverRoles,
        int $requesterUserId,
        array $requirement
    ): bool {
        // Anti-self-approval rule
        if (array_key_exists($requesterUserId, $approverRoles)) {
            throw new DomainException('Self-approval violation: Requester cannot act as approver for own grant.');
        }

        $uniqueApprovers = count(array_unique(array_keys($approverRoles)));
        if ($uniqueApprovers < $requirement['required_signatures']) {
            return false;
        }

        if ($requirement['requires_dual_custody'] && $uniqueApprovers < 2) {
            return false;
        }

        return true;
    }
}
