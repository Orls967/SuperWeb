<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class CabQuorumEvaluator
{
    /**
     * Mandatory representative roles required for CAB approval quorum.
     *
     * @var list<string>
     */
    private const REQUIRED_ROLES = [
        'security_officer',
        'infra_lead',
        'qa_director',
        'business_domain_owner',
    ];

    /**
     * Evaluates whether recorded CAB approvals satisfy enterprise quorum policy.
     *
     * @param  list<array{role: string, approved: bool, timestamp: string, approver_email: string}>  $votes
     * @return array{quorum_reached: bool, approved_count: int, required_count: int, missing_roles: list<string>, consensus_percentage: float}
     */
    public function evaluateQuorum(array $votes, string $riskTier = 'high'): array
    {
        $approvedRoles = [];
        $rejectedCount = 0;

        foreach ($votes as $vote) {
            if ($vote['approved'] ?? false) {
                $role = $vote['role'] ?? 'unknown';
                $approvedRoles[$role] = true;
            } else {
                $rejectedCount++;
            }
        }

        $missingRoles = [];
        foreach (self::REQUIRED_ROLES as $reqRole) {
            if (! isset($approvedRoles[$reqRole])) {
                $missingRoles[] = $reqRole;
            }
        }

        $approvedCount = count($approvedRoles);
        $totalRequired = count(self::REQUIRED_ROLES);

        // In critical tier, unanimous quorum with zero rejections is mandatory
        $quorumReached = false;
        if ($riskTier === 'critical') {
            $quorumReached = ($missingRoles === [] && $rejectedCount === 0);
        } else {
            // High tier allows 3 of 4 approvals provided no security rejection
            $quorumReached = ($approvedCount >= 3 && ! in_array('security_officer', $missingRoles, true));
        }

        $consensusPct = $totalRequired > 0 ? round(($approvedCount / $totalRequired) * 100.0, 1) : 0.0;

        return [
            'quorum_reached' => $quorumReached,
            'approved_count' => $approvedCount,
            'required_count' => $totalRequired,
            'missing_roles' => $missingRoles,
            'consensus_percentage' => $consensusPct,
        ];
    }
}
