<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\StateMachines;

use InvalidArgumentException;

final class SopCycleStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['demand_review'],
        'demand_review' => ['supply_review', 'draft'],
        'supply_review' => ['pre_sop_balancing', 'demand_review'],
        'pre_sop_balancing' => ['executive_signoff', 'supply_review'],
        'executive_signoff' => ['published', 'pre_sop_balancing'],
        'published' => ['archived'],
        'archived' => [],
    ];

    /**
     * @return list<string>
     */
    public static function states(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function assertTransition(string $from, string $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException("INVALID_SOP_TRANSITION: Tidak dapat berpindah dari [{$from}] ke [{$to}].");
        }
    }
}
