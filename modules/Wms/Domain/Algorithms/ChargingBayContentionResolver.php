<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Pengalokasi bay pengisian daya robotika berbasis pewarnaan interval (Interval Graph Coloring)
 * agar tidak ada dua robot yang menempati bay fisik yang sama pada jendela waktu bertumpuk.
 */
final class ChargingBayContentionResolver
{
    public const int DEFAULT_MAX_BAYS = 4;

    /**
     * @param  list<array{robot_id:int, window_start_min:int, window_end_min:int, priority?:int}>  $requests
     * @return array{
     *     assigned:list<array{robot_id:int, bay_code:string, window_start_min:int, window_end_min:int}>,
     *     deferred_robot_ids:list<int>,
     *     bays_used:int
     * }
     */
    public function assignBays(array $requests, int $maxBays = self::DEFAULT_MAX_BAYS): array
    {
        if ($maxBays < 1) {
            throw new InvalidArgumentException('Jumlah maksimum bay charger minimal 1.');
        }

        $sorted = $requests;
        usort($sorted, static function (array $a, array $b): int {
            $cmpStart = (int) $a['window_start_min'] <=> (int) $b['window_start_min'];
            if ($cmpStart !== 0) {
                return $cmpStart;
            }

            return (int) $a['robot_id'] <=> (int) $b['robot_id'];
        });

        /** @var array<int, int> $bayFreeAt */
        $bayFreeAt = [];
        for ($b = 1; $b <= $maxBays; $b++) {
            $bayFreeAt[$b] = 0;
        }

        $assigned = [];
        $deferred = [];
        $usedBaySet = [];

        foreach ($sorted as $req) {
            $robotId = (int) $req['robot_id'];
            $start = max(0, (int) $req['window_start_min']);
            $end = max($start + 1, (int) $req['window_end_min']);

            $chosenBay = null;
            for ($b = 1; $b <= $maxBays; $b++) {
                if ($bayFreeAt[$b] <= $start) {
                    $chosenBay = $b;
                    break;
                }
            }

            if ($chosenBay === null) {
                $deferred[] = $robotId;

                continue;
            }

            $bayFreeAt[$chosenBay] = $end;
            $usedBaySet[$chosenBay] = true;
            $assigned[] = [
                'robot_id' => $robotId,
                'bay_code' => sprintf('BAY-%02d', $chosenBay),
                'window_start_min' => $start,
                'window_end_min' => $end,
            ];
        }

        return [
            'assigned' => $assigned,
            'deferred_robot_ids' => $deferred,
            'bays_used' => count($usedBaySet),
        ];
    }
}
