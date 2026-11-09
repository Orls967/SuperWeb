<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Enums\ChangeType;
use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeFreezingScheduleManager
{
    /**
     * Pre-configured organization freeze windows
     *
     * @var list<array{name: string, start: string, end: string, reason: string}>
     */
    private const SCHEDULED_FREEZES = [
        [
            'name' => 'YEAR_END_FREEZE',
            'start' => '12-20',
            'end' => '01-05',
            'reason' => 'Year-end holiday traffic stability window',
        ],
        [
            'name' => 'BLACK_FRIDAY_FREEZE',
            'start' => '11-20',
            'end' => '12-01',
            'reason' => 'Peak commercial shopping traffic window',
        ],
    ];

    /**
     * @return array{
     *     is_frozen: bool,
     *     active_freeze_name: string|null,
     *     reason: string|null,
     *     allows_change: bool
     * }
     */
    public function evaluateFreezeWindow(ChangeRequest $changeRequest, ?CarbonImmutable $evalTime = null): array
    {
        $now = $evalTime ?? CarbonImmutable::now();
        $monthDay = $now->format('m-d');

        $activeFreeze = null;
        foreach (self::SCHEDULED_FREEZES as $freeze) {
            $start = $freeze['start'];
            $end = $freeze['end'];

            if ($start <= $end) {
                if ($monthDay >= $start && $monthDay <= $end) {
                    $activeFreeze = $freeze;
                    break;
                }
            } else {
                // Year wrap-around (e.g. 12-20 to 01-05)
                if ($monthDay >= $start || $monthDay <= $end) {
                    $activeFreeze = $freeze;
                    break;
                }
            }
        }

        $isFrozen = $activeFreeze !== null;
        $isEmergency = $changeRequest->type === ChangeType::Emergency;
        $allowsChange = ! $isFrozen || $isEmergency;

        return [
            'is_frozen' => $isFrozen,
            'active_freeze_name' => $activeFreeze['name'] ?? null,
            'reason' => $activeFreeze['reason'] ?? null,
            'allows_change' => $allowsChange,
        ];
    }
}
