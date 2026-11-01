<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

class TimeOfUseTariffCalculator
{
    public function __construct(
        private readonly LargestRemainder $largestRemainder = new LargestRemainder
    ) {}

    /**
     * @param  list<array{name: string, start: string, end: string, rate: int, cross_midnight?: bool}>  $windows
     * @return array<string, int>
     */
    public function apportionEnergy(
        CarbonInterface $fromTime,
        CarbonInterface $toTime,
        int $energyDeltaWh,
        array $windows
    ): array {
        if ($energyDeltaWh <= 0 || empty($windows)) {
            $res = [];
            foreach ($windows as $w) {
                $res[$w['name']] = 0;
            }

            return $res;
        }

        $from = Carbon::instance($fromTime);
        $to = Carbon::instance($toTime);

        $totalMinutes = max(1, (int) $from->diffInMinutes($to));
        $overlapMinutes = [];

        foreach ($windows as $w) {
            $overlapMinutes[$w['name']] = 0;
        }

        // Loop per menit untuk menghitung irisan secara presisi
        $curr = $from->copy();
        while ($curr->lt($to)) {
            $timeStr = $curr->format('H:i');

            foreach ($windows as $w) {
                $start = $w['start'];
                $end = $w['end'];
                $cross = ! empty($w['cross_midnight']) || (! empty($w['is_cross_midnight']));

                $inWindow = false;
                if (! $cross) {
                    if ($start <= $end) {
                        $inWindow = ($timeStr >= $start && $timeStr < $end);
                    } else {
                        $inWindow = ($timeStr >= $start || $timeStr < $end);
                    }
                } else {
                    $inWindow = ($timeStr >= $start || $timeStr < $end);
                }

                if ($inWindow) {
                    $overlapMinutes[$w['name']]++;
                    break;
                }
            }

            $curr->addMinute();
        }

        $items = [];
        foreach ($windows as $w) {
            $name = $w['name'];
            $items[] = [
                'key' => $name,
                'weight' => $overlapMinutes[$name] ?? 0,
            ];
        }

        // Jika semua bobot 0, berikan semua ke jendela pertama
        $totalWeight = array_sum(array_column($items, 'weight'));
        if ($totalWeight === 0) {
            $items[0]['weight'] = 1;
        }

        return $this->largestRemainder->apportion($energyDeltaWh, $items);
    }
}
