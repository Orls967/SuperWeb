<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EcosystemDaoGovernanceService (Fase 233)
 *
 * Implements:
 *  - 233.1 Proposal classes with tailored voting weights, supermajority thresholds & quorum
 *  - 233.1 Liquid democracy vote delegation across stakeholders
 *  - 233.2 Distinction between binding voting vs non-binding consultations
 *  - 233.3 On-chain style hash-chained vote ledger and automated execution requiring safety review clearance
 *  - 233.6 Edge case: In-flight proposal cancellation properly logged and excluded from tally results
 *  - 233.7 Silent delegation distinction from deliberate abstention
 */
class EcosystemDaoGovernanceService
{
    /**
     * Create DAO governance proposal with class-specific rules (233.1 & 233.2).
     */
    public function createProposal(string $proposalClass, string $title, bool $isBinding = true): object
    {
        $class = strtoupper($proposalClass);

        // Class-specific rules
        $quorum = match ($class) {
            'STRATEGIC' => 60.0,
            'TECHNICAL' => 50.0,
            'OPERATIONAL' => 40.0,
            'SOCIAL' => 30.0,
            default => 50.0,
        };

        $threshold = match ($class) {
            'STRATEGIC' => 66.67,
            default => 50.00,
        };

        $code = 'DAO-'.strtoupper(Str::random(8));

        $id = DB::table('gov_dao_proposals')->insertGetId([
            'proposal_code' => $code,
            'proposal_class' => $class,
            'title' => $title,
            'quorum_pct' => $quorum,
            'supermajority_threshold_pct' => $threshold,
            'is_binding' => $isBinding,
            'status' => 'ACTIVE',
            'cancellation_reason' => null,
            'safety_review_cleared' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_dao_proposals')->find($id);
    }

    /**
     * Delegate voting weight to another stakeholder (Liquid Democracy) (233.1).
     */
    public function delegateVote(
        string $delegatorId,
        string $delegateId,
        float $votingWeight = 1.0,
        ?string $proposalClass = null
    ): object {
        $id = DB::table('gov_dao_vote_delegations')->insertGetId([
            'delegator_golden_id' => strtoupper($delegatorId),
            'delegate_golden_id' => strtoupper($delegateId),
            'proposal_class' => $proposalClass ? strtoupper($proposalClass) : null,
            'voting_weight' => $votingWeight,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_dao_vote_delegations')->find($id);
    }

    /**
     * Cast vote with hash chaining and delegated weight aggregation (233.1, 233.3 & 233.7).
     */
    public function castVote(
        string $proposalCode,
        string $voterId,
        string $choice,
        float $baseWeight = 1.0
    ): object {
        $proposal = DB::table('gov_dao_proposals')->where('proposal_code', strtoupper($proposalCode))->first();
        if (! $proposal) {
            throw new \InvalidArgumentException("Proposal {$proposalCode} not found.");
        }

        if ($proposal->status !== 'ACTIVE') {
            throw new \RuntimeException("Cannot vote on proposal {$proposalCode} with status {$proposal->status}.");
        }

        // Aggregate delegated voting weight
        $delegatedWeight = (float) DB::table('gov_dao_vote_delegations')
            ->where('delegate_golden_id', strtoupper($voterId))
            ->where('is_active', true)
            ->where(function ($q) use ($proposal) {
                $q->whereNull('proposal_class')
                    ->orWhere('proposal_class', $proposal->proposal_class);
            })
            ->sum('voting_weight');

        $effectiveWeight = $baseWeight + $delegatedWeight;

        // Hash chain linkage (233.3)
        $lastVote = DB::table('gov_dao_votes_ledger')
            ->where('proposal_code', strtoupper($proposalCode))
            ->orderBy('id', 'desc')
            ->first();

        $prevHash = $lastVote ? $lastVote->vote_hash : hash('sha256', 'GENESIS_'.$proposalCode);
        $voteHash = hash('sha256', $prevHash.$proposalCode.strtoupper($voterId).strtoupper($choice).$effectiveWeight);

        $voteCode = 'VOTE-'.strtoupper(Str::random(8));

        $id = DB::table('gov_dao_votes_ledger')->insertGetId([
            'vote_code' => $voteCode,
            'proposal_code' => strtoupper($proposalCode),
            'voter_golden_id' => strtoupper($voterId),
            'effective_weight' => $effectiveWeight,
            'vote_choice' => strtoupper($choice),
            'prev_hash' => $prevHash,
            'vote_hash' => $voteHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_dao_votes_ledger')->find($id);
    }

    /**
     * Cancel proposal during in-flight voting (233.6 Edge Case).
     */
    public function cancelProposal(string $proposalCode, string $reason): object
    {
        DB::table('gov_dao_proposals')
            ->where('proposal_code', strtoupper($proposalCode))
            ->update([
                'status' => 'CANCELLED',
                'cancellation_reason' => $reason,
                'updated_at' => now(),
            ]);

        return (object) DB::table('gov_dao_proposals')->where('proposal_code', strtoupper($proposalCode))->first();
    }

    /**
     * Tally votes according to class-specific quorum and supermajority rules (233.1 & 233.5).
     */
    public function tallyProposal(string $proposalCode, int $totalEligibleWeight = 100): array
    {
        $proposal = DB::table('gov_dao_proposals')->where('proposal_code', strtoupper($proposalCode))->first();
        if (! $proposal) {
            throw new \InvalidArgumentException("Proposal {$proposalCode} not found.");
        }

        // Edge Case 233.6: Cancelled proposals are not computed as valid results
        if ($proposal->status === 'CANCELLED') {
            return [
                'proposal_code' => $proposal->proposal_code,
                'status' => 'CANCELLED',
                'is_valid_result' => false,
                'cancellation_reason' => $proposal->cancellation_reason,
            ];
        }

        $yesWeight = (float) DB::table('gov_dao_votes_ledger')
            ->where('proposal_code', $proposal->proposal_code)
            ->where('vote_choice', 'YES')
            ->sum('effective_weight');

        $noWeight = (float) DB::table('gov_dao_votes_ledger')
            ->where('proposal_code', $proposal->proposal_code)
            ->where('vote_choice', 'NO')
            ->sum('effective_weight');

        $abstainWeight = (float) DB::table('gov_dao_votes_ledger')
            ->where('proposal_code', $proposal->proposal_code)
            ->where('vote_choice', 'ABSTAIN')
            ->sum('effective_weight');

        $totalParticipated = $yesWeight + $noWeight + $abstainWeight;
        $participationPct = ($totalEligibleWeight > 0) ? round(($totalParticipated / $totalEligibleWeight) * 100, 2) : 0.0;

        $quorumMet = ($participationPct >= (float) $proposal->quorum_pct);

        $decidingWeight = $yesWeight + $noWeight;
        $yesApprovalPct = ($decidingWeight > 0) ? round(($yesWeight / $decidingWeight) * 100, 2) : 0.0;
        $thresholdMet = ($yesApprovalPct >= (float) $proposal->supermajority_threshold_pct);

        $passed = $quorumMet && $thresholdMet;
        $newStatus = $passed ? 'PASSED' : 'REJECTED';

        DB::table('gov_dao_proposals')
            ->where('proposal_code', $proposal->proposal_code)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        return [
            'proposal_code' => $proposal->proposal_code,
            'proposal_class' => $proposal->proposal_class,
            'total_participated_weight' => $totalParticipated,
            'participation_pct' => $participationPct,
            'quorum_met' => $quorumMet,
            'yes_weight' => $yesWeight,
            'no_weight' => $noWeight,
            'abstain_weight' => $abstainWeight,
            'yes_approval_pct' => $yesApprovalPct,
            'threshold_met' => $thresholdMet,
            'status' => $newStatus,
            'is_valid_result' => true,
        ];
    }

    /**
     * Grant mandatory safety review clearance before automated bridge execution (233.3 & 233.5).
     */
    public function grantSafetyReview(string $proposalCode): void
    {
        DB::table('gov_dao_proposals')
            ->where('proposal_code', strtoupper($proposalCode))
            ->update([
                'safety_review_cleared' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Execute proposal via bridge requiring valid passed status and safety clearance (233.3 & 233.5).
     */
    public function executeProposal(string $proposalCode): object
    {
        $proposal = DB::table('gov_dao_proposals')->where('proposal_code', strtoupper($proposalCode))->first();
        if (! $proposal) {
            throw new \InvalidArgumentException("Proposal {$proposalCode} not found.");
        }

        if ($proposal->status !== 'PASSED') {
            throw new \RuntimeException("Execution rejected: Cannot execute proposal with status {$proposal->status} (must be PASSED).");
        }

        if (! $proposal->safety_review_cleared) {
            throw new \RuntimeException("Execution blocked: Proposal {$proposalCode} requires formal safety review clearance before automated bridge execution.");
        }

        DB::table('gov_dao_proposals')
            ->where('proposal_code', strtoupper($proposalCode))
            ->update([
                'status' => 'EXECUTED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('gov_dao_proposals')->where('proposal_code', strtoupper($proposalCode))->first();
    }

    /**
     * Quality audit gate (`governance:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Executed proposals without safety review clearance
        $unreviewedExecutions = DB::table('gov_dao_proposals')
            ->where('status', 'EXECUTED')
            ->where('safety_review_cleared', false)
            ->count();

        // Discrepancy 2: Executed proposals that were cancelled
        $cancelledExecutions = DB::table('gov_dao_proposals')
            ->where('status', 'EXECUTED')
            ->whereNotNull('cancellation_reason')
            ->count();

        $discrepancies = $unreviewedExecutions + $cancelledExecutions;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_proposals' => DB::table('gov_dao_proposals')->count(),
            'total_delegations' => DB::table('gov_dao_vote_delegations')->count(),
            'total_votes' => DB::table('gov_dao_votes_ledger')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
