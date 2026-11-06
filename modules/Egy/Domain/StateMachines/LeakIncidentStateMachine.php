<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\StateMachines;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\LeakIncident;

final class LeakIncidentStateMachine
{
    public const STATUS_DETECTED = 'detected';

    public const STATUS_DISPATCHED = 'dispatched_work_order';

    public const STATUS_REPAIRED = 'repaired';

    public const STATUS_VERIFIED = 'verified_closed';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DETECTED => [self::STATUS_DISPATCHED],
        self::STATUS_DISPATCHED => [self::STATUS_REPAIRED],
        self::STATUS_REPAIRED => [self::STATUS_VERIFIED],
        self::STATUS_VERIFIED => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public function transition(LeakIncident $incident, string $to): void
    {
        $current = $incident->status;

        if (! $this->canTransition($current, $to)) {
            throw new InvalidArgumentException("Transisi insiden kebocoran tidak sah dari '{$current}' ke '{$to}'");
        }

        $incident->status = $to;
        $incident->save();
    }
}
