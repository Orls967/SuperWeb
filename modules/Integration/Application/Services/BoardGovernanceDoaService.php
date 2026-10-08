<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BoardGovernanceDoaService (Fase 231)
 *
 * Implements:
 *  - 231.1 Board composition, charters & immutable decision registers
 *  - 231.2 Delegation of Authority (DoA) matrix enforcement across financial tiers
 *  - 231.3 Conflict of interest register & mandatory abstain enforcement
 *  - 231.4 Full decision traceability (alternatives evaluated, dissenting opinions)
 *  - 231.6 Edge case: Adjournment protocol when meeting quorum is not achieved
 *  - 231.7 Edge case: Designating temporary alternate member for conflicted board directors
 */
class BoardGovernanceDoaService
{
    /**
     * Set or update DoA threshold for a decision type & authority tier (231.2).
     */
    public function setDoaLimit(
        string $decisionType,
        string $authorityLevel,
        float $minValue,
        float $maxValue
    ): object {
        DB::table('gov_delegation_of_authority_matrix')->updateOrInsert(
            [
                'decision_type' => strtoupper($decisionType),
                'authority_level' => strtoupper($authorityLevel),
            ],
            [
                'min_value' => $minValue,
                'max_value' => $maxValue,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('gov_delegation_of_authority_matrix')
            ->where('decision_type', strtoupper($decisionType))
            ->where('authority_level', strtoupper($authorityLevel))
            ->first();
    }

    /**
     * Validate approval against DoA matrix boundaries (231.2 & 231.5).
     */
    public function validateDoaCompliance(string $decisionType, string $authorityLevel, float $value): bool
    {
        $doa = DB::table('gov_delegation_of_authority_matrix')
            ->where('decision_type', strtoupper($decisionType))
            ->where('authority_level', strtoupper($authorityLevel))
            ->first();

        if (! $doa) {
            throw new \InvalidArgumentException("No DoA matrix rule found for {$decisionType} at level {$authorityLevel}.");
        }

        if ($value > (float) $doa->max_value) {
            throw new \InvalidArgumentException(
                "DoA violation: Approval value {$value} exceeds allowable threshold ({$doa->max_value}) for authority level {$authorityLevel}."
            );
        }

        return true;
    }

    /**
     * Record board meeting and calculate quorum factoring in abstentions (231.3, 231.5 & 231.6 Edge Case).
     */
    public function recordBoardMeeting(
        string $meetingCode,
        string $committeeType,
        string $scheduledDate,
        int $totalEligibleMembers,
        int $attendingMembersCount,
        int $abstainedMembersCount = 0
    ): object {
        // 231.3 & 231.5: Abstentions adjust the active voting quorum base
        $activeVotingQuorum = max(1, $totalEligibleMembers - $abstainedMembersCount);
        $requiredMajority = (int) ceil($activeVotingQuorum * 0.50);

        $quorumAchieved = ($attendingMembersCount >= $requiredMajority);

        // 231.6 Edge Case: Quorum not met -> Meeting is formally adjourned with documented reason
        $status = $quorumAchieved ? 'QUORUM_MET' : 'ADJOURNED_LACK_OF_QUORUM';
        $reason = $quorumAchieved ? null : "Meeting adjourned: attendance of {$attendingMembersCount} failed to meet majority quorum requirement of {$requiredMajority} (active voting base: {$activeVotingQuorum}).";

        $id = DB::table('gov_board_meetings')->insertGetId([
            'meeting_code' => strtoupper($meetingCode),
            'committee_type' => strtoupper($committeeType),
            'scheduled_date' => $scheduledDate,
            'total_eligible_members' => $totalEligibleMembers,
            'attending_members_count' => $attendingMembersCount,
            'abstained_members_count' => $abstainedMembersCount,
            'active_voting_quorum' => $activeVotingQuorum,
            'quorum_achieved' => $quorumAchieved,
            'status' => $status,
            'adjournment_reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_board_meetings')->find($id);
    }

    /**
     * Declare conflict of interest and designate temporary alternate member (231.3 & 231.7 Edge Case).
     */
    public function declareConflictOfInterest(
        string $boardMemberId,
        string $relatedEntityOrVendor,
        ?string $alternateMemberId = null
    ): object {
        $code = 'COI-'.strtoupper(Str::random(8));

        $id = DB::table('gov_conflict_of_interest_declarations')->insertGetId([
            'declaration_code' => $code,
            'board_member_id' => strtoupper($boardMemberId),
            'related_entity_or_vendor' => $relatedEntityOrVendor,
            'mandatory_abstain' => true,
            'alternate_member_id' => $alternateMemberId ? strtoupper($alternateMemberId) : null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_conflict_of_interest_declarations')->find($id);
    }

    /**
     * Record immutable decision into register with audit trail (231.4 & 231.5).
     */
    public function recordDecision(
        string $decisionType,
        float $decisionValue,
        string $approvedByLevel,
        string $approvedByEntity,
        ?string $meetingCode = null,
        array $alternativesEvaluated = [],
        array $dissentsRecorded = []
    ): object {
        // If meeting is attached, verify quorum was met
        if ($meetingCode !== null) {
            $meeting = DB::table('gov_board_meetings')->where('meeting_code', strtoupper($meetingCode))->first();
            if ($meeting && ! $meeting->quorum_achieved) {
                throw new \RuntimeException(
                    "Decision registration rejected: Cannot record formal board decision for meeting {$meetingCode} that lacked quorum."
                );
            }
        }

        // Enforce DoA compliance before recording
        $this->validateDoaCompliance($decisionType, $approvedByLevel, $decisionValue);

        $code = 'DEC-'.strtoupper(Str::random(8));

        $id = DB::table('gov_decision_registers')->insertGetId([
            'decision_code' => $code,
            'decision_type' => strtoupper($decisionType),
            'decision_value' => $decisionValue,
            'approved_by_level' => strtoupper($approvedByLevel),
            'approved_by_entity' => $approvedByEntity,
            'meeting_code' => $meetingCode ? strtoupper($meetingCode) : null,
            'alternatives_evaluated' => json_encode($alternativesEvaluated),
            'dissents_recorded' => json_encode($dissentsRecorded),
            'is_immutable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_decision_registers')->find($id);
    }

    /**
     * Quality audit gate (`gov:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Decisions exceeding DoA limits for their approval tier
        $decisions = DB::table('gov_decision_registers')->get();
        $doaViolations = 0;
        foreach ($decisions as $d) {
            $doa = DB::table('gov_delegation_of_authority_matrix')
                ->where('decision_type', $d->decision_type)
                ->where('authority_level', $d->approved_by_level)
                ->first();

            if ($doa && ((float) $d->decision_value > (float) $doa->max_value)) {
                $doaViolations++;
            }
        }

        // Discrepancy 2: Decisions associated with meetings that lacked quorum
        $invalidMeetingDecisions = DB::table('gov_decision_registers as d')
            ->join('gov_board_meetings as m', 'd.meeting_code', '=', 'm.meeting_code')
            ->where('m.quorum_achieved', false)
            ->count();

        // Discrepancy 3: Active COI without mandatory abstain
        $unabstainedCoi = DB::table('gov_conflict_of_interest_declarations')
            ->where('status', 'ACTIVE')
            ->where('mandatory_abstain', false)
            ->count();

        $discrepancies = $doaViolations + $invalidMeetingDecisions + $unabstainedCoi;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_doa_rules' => DB::table('gov_delegation_of_authority_matrix')->count(),
            'total_board_meetings' => DB::table('gov_board_meetings')->count(),
            'total_coi_declarations' => DB::table('gov_conflict_of_interest_declarations')->count(),
            'total_decisions_recorded' => DB::table('gov_decision_registers')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
