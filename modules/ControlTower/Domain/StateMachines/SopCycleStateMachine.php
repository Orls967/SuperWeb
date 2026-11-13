<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\StateMachines;

use InvalidArgumentException;

final class SopCycleStateMachine
{
    /** @var list<string> */
    private const CANONICAL_STATES = [
        'data_gathering',
        'demand_review',
        'supply_review',
        'pre_sop',
        'exec_sop',
        'published',
        'closed',
    ];

    /** @var array<string, int> */
    private const STAGE_ORDER = [
        'draft' => 0,
        'data_gathering' => 0,
        'demand_review' => 1,
        'supply_review' => 2,
        'pre_sop_balancing' => 3,
        'pre_sop' => 3,
        'executive_signoff' => 4,
        'exec_sop' => 4,
        'published' => 5,
        'archived' => 6,
        'closed' => 6,
    ];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        // Legacy 7-stage flow (preserved for backward compatibility)
        'draft' => ['demand_review'],
        'pre_sop_balancing' => ['executive_signoff', 'supply_review'],
        'executive_signoff' => ['published', 'pre_sop_balancing'],
        'archived' => [],

        // Canonical 7-stage flow + shared states
        'data_gathering' => ['demand_review'],
        'demand_review' => ['supply_review', 'data_gathering', 'draft'],
        'supply_review' => ['pre_sop', 'pre_sop_balancing', 'demand_review'],
        'pre_sop' => ['exec_sop', 'supply_review'],
        'exec_sop' => ['published', 'pre_sop'],
        'published' => ['closed', 'archived'],
        'closed' => [],
    ];

    /**
     * @return list<string>
     */
    public static function states(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    /**
     * @return list<string>
     */
    public static function canonicalStates(): array
    {
        return self::CANONICAL_STATES;
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isBackwardTransition(string $from, string $to): bool
    {
        if (! $this->canTransition($from, $to)) {
            return false;
        }

        return (self::STAGE_ORDER[$to] ?? 0) < (self::STAGE_ORDER[$from] ?? 0);
    }

    public function assertTransition(string $from, string $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException("INVALID_SOP_TRANSITION: Tidak dapat berpindah dari [{$from}] ke [{$to}].");
        }
    }

    public function assertTransitionWithReason(string $from, string $to, ?string $reason): void
    {
        $this->assertTransition($from, $to);

        if ($this->isBackwardTransition($from, $to) && trim((string) $reason) === '') {
            throw new InvalidArgumentException("BACKWARD_TRANSITION_REASON_REQUIRED: Transisi mundur dari [{$from}] ke [{$to}] wajib menyertakan alasan.");
        }
    }
}
