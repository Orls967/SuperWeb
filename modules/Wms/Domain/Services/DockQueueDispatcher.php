<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Penjadwal antrean truk pada pintu dock (inbound vs outbound carrier cutoff).
 */
final class DockQueueDispatcher
{
    /**
     * @param  list<array{appointment_id: string, direction: string, minutes_to_cutoff: int, pallet_count: int}>  $queue
     * @param  list<string>  $availableDoors
     * @return list<array{appointment_id: string, assigned_door: string, priority_rank: int}>
     */
    public function assignDoors(array $queue, array $availableDoors): array
    {
        if ($queue === [] || $availableDoors === []) {
            return [];
        }

        $sorted = $queue;
        usort($sorted, static function (array $a, array $b): int {
            $dirScoreA = $a['direction'] === 'outbound' && $a['minutes_to_cutoff'] <= 120 ? 0 : 1;
            $dirScoreB = $b['direction'] === 'outbound' && $b['minutes_to_cutoff'] <= 120 ? 0 : 1;

            return [$dirScoreA, $a['minutes_to_cutoff'], -$a['pallet_count'], $a['appointment_id']]
                <=> [$dirScoreB, $b['minutes_to_cutoff'], -$b['pallet_count'], $b['appointment_id']];
        });

        $doorCount = count($availableDoors);
        $assignments = [];

        foreach ($sorted as $idx => $item) {
            $assignments[] = [
                'appointment_id' => (string) $item['appointment_id'],
                'assigned_door' => $availableDoors[$idx % $doorCount],
                'priority_rank' => $idx + 1,
            ];
        }

        return $assignments;
    }
}
