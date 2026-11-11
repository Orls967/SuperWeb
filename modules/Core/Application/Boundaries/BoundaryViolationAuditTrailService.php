<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Carbon\Carbon;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\Models\CoreBoundaryViolationTransition;

/**
 * Audit trail and forensic timeline service for boundary violation state transitions (DR4-1).
 *
 * Provides:
 * - Immutable chronology of state transitions per violation
 * - State dwell time (duration elapsed in each phase)
 * - Actor accountability ledger (who approved, migrated, verified, or waived)
 * - Forensic verification of commit SHA linkers for closed violations
 */
final class BoundaryViolationAuditTrailService
{
    /**
     * Retrieve complete chronological timeline for a violation.
     *
     * @param  int  $violationId
     * @return array{
     *     violationId: int,
     *     ruleId: string,
     *     currentStatus: string,
     *     totalTransitions: int,
     *     timeline: list<array{
     *         transitionId: int,
     *         fromStatus: string,
     *         toStatus: string,
     *         actor: string,
     *         reason: string,
     *         commitSha: ?string,
     *         occurredAt: string,
     *         dwellHours: int
     *     }>,
     *     dwellTimesByStatus: array<string, int>,
     *     leadTimeHours: int
     * }
     */
    public function getTimeline(int $violationId): array
    {
        $violation = CoreBoundaryViolation::findOrFail($violationId);
        $transitions = CoreBoundaryViolationTransition::where('violation_id', $violationId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $timeline = [];
        $dwellByStatus = [];
        $prevTime = null;
        $firstTime = null;
        $lastTime = null;

        foreach ($transitions as $t) {
            $currTime = Carbon::parse($t->occurred_at);
            if ($firstTime === null) {
                $firstTime = $currTime;
            }
            $lastTime = $currTime;

            $dwellHours = 0;
            if ($prevTime !== null) {
                $dwellHours = max(0, (int) $prevTime->diffInHours($currTime));
                $dwellByStatus[$t->from_status] = ($dwellByStatus[$t->from_status] ?? 0) + $dwellHours;
            }

            $timeline[] = [
                'transitionId' => (int) $t->id,
                'fromStatus' => $t->from_status,
                'toStatus' => $t->to_status,
                'actor' => $t->actor,
                'reason' => $t->reason,
                'commitSha' => $t->commit_sha,
                'occurredAt' => $currTime->toIso8601String(),
                'dwellHours' => $dwellHours,
            ];

            $prevTime = $currTime;
        }

        $leadTime = ($firstTime !== null && $lastTime !== null)
            ? (int) $firstTime->diffInHours($lastTime)
            : 0;

        $currentStatus = $violation->status instanceof \BackedEnum
            ? $violation->status->value
            : (string) $violation->status;

        return [
            'violationId' => $violationId,
            'ruleId' => $violation->rule_id,
            'currentStatus' => $currentStatus,
            'totalTransitions' => $transitions->count(),
            'timeline' => $timeline,
            'dwellTimesByStatus' => $dwellByStatus,
            'leadTimeHours' => $leadTime,
        ];
    }

    /**
     * Audit actor accountability: count transitions performed by each actor.
     *
     * @return array<string, array{totalActions: int, waivedCount: int, closedCount: int, triagedCount: int}>
     */
    public function getActorSummary(): array
    {
        $transitions = CoreBoundaryViolationTransition::all();
        $summary = [];

        foreach ($transitions as $t) {
            $actor = $t->actor;
            if (! isset($summary[$actor])) {
                $summary[$actor] = [
                    'totalActions' => 0,
                    'waivedCount' => 0,
                    'closedCount' => 0,
                    'triagedCount' => 0,
                ];
            }

            $summary[$actor]['totalActions']++;

            if ($t->to_status === 'waived') {
                $summary[$actor]['waivedCount']++;
            } elseif ($t->to_status === 'closed') {
                $summary[$actor]['closedCount']++;
            } elseif ($t->to_status === 'triaged') {
                $summary[$actor]['triagedCount']++;
            }
        }

        ksort($summary);

        return $summary;
    }
}

