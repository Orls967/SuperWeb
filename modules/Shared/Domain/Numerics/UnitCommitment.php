<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Daily Unit Commitment solver using Dynamic Programming over unit on/off states per hour.
 *
 * Designed for n <= 8 generating units over H hours.
 * Complexity: O(H * 4^N) in worst-case state transitions (O(H * 2^N) state space).
 */
final class UnitCommitment
{
    /**
     * @param  list<array{code: string, min_mw: int, max_mw: int, marginal_cost: int, startup_cost: int}>  $units
     * @param  list<int>  $hourlyDemands
     * @param  list<string>  $initialOnlineCodes  Units online before hour 0
     * @return array{
     *     status: 'OPTIMAL'|'INFEASIBLE',
     *     causing_hour: int|null,
     *     total_cost: int,
     *     hourly_schedule: list<array{
     *         hour: int,
     *         demand: int,
     *         online_units: list<string>,
     *         dispatches: array<string, int>,
     *         dispatch_cost: int,
     *         startup_cost: int,
     *         total_cost: int
     *     }>,
     *     certificate: array<string, mixed>|null
     * }
     *
     * @throws InvalidArgumentException
     */
    public static function solve(array $units, array $hourlyDemands, array $initialOnlineCodes = []): array
    {
        $n = count($units);
        if ($n === 0) {
            throw new InvalidArgumentException('Units list cannot be empty.');
        }
        if ($n > 8) {
            throw new InvalidArgumentException("Unit commitment exact DP supports up to 8 units, got {$n}.");
        }

        $h = count($hourlyDemands);
        if ($h === 0) {
            throw new InvalidArgumentException('Hourly demands list cannot be empty.');
        }

        // Sort units deterministically by code ascending
        $sortedUnits = $units;
        usort($sortedUnits, fn (array $a, array $b): int => strcmp($a['code'], $b['code']));

        // Validate units
        $unitByCode = [];
        foreach ($sortedUnits as $idx => $u) {
            if ($u['min_mw'] < 0 || $u['max_mw'] < $u['min_mw'] || $u['marginal_cost'] < 0 || $u['startup_cost'] < 0) {
                throw new InvalidArgumentException("Invalid parameters for unit '{$u['code']}'.");
            }
            if (isset($unitByCode[$u['code']])) {
                throw new InvalidArgumentException("Duplicate unit code '{$u['code']}'.");
            }
            $unitByCode[$u['code']] = $u;
        }

        // Determine initial state bitmask
        $initialMask = 0;
        foreach ($initialOnlineCodes as $code) {
            $found = false;
            foreach ($sortedUnits as $idx => $u) {
                if ($u['code'] === $code) {
                    $initialMask |= (1 << $idx);
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new InvalidArgumentException("Initial unit code '{$code}' not found in units list.");
            }
        }

        $numStates = 1 << $n;

        // DP table: dp[t][mask] = min cost, parent[t][mask] = prev_mask, dispatchInfo[t][mask] = result
        $dp = [];
        $parent = [];
        $dispatchInfo = [];

        // Pre-solve MeritOrderDispatch for each state and hour
        for ($t = 0; $t < $h; $t++) {
            $demand = $hourlyDemands[$t];
            if ($demand < 0) {
                throw new InvalidArgumentException("Demand at hour {$t} cannot be negative: {$demand}");
            }

            $dp[$t] = [];
            $parent[$t] = [];
            $dispatchInfo[$t] = [];

            for ($mask = 0; $mask < $numStates; $mask++) {
                $active = [];
                for ($i = 0; $i < $n; $i++) {
                    if (($mask & (1 << $i)) !== 0) {
                        $active[] = $sortedUnits[$i];
                    }
                }

                $disp = MeritOrderDispatch::dispatch($active, $demand);
                if ($disp !== null) {
                    $dispatchInfo[$t][$mask] = $disp;
                }
            }

            // If no state is capable of meeting demand at hour t
            if (empty($dispatchInfo[$t])) {
                return [
                    'status' => 'INFEASIBLE',
                    'causing_hour' => $t,
                    'total_cost' => 0,
                    'hourly_schedule' => [],
                    'certificate' => null,
                ];
            }

            if ($t === 0) {
                foreach ($dispatchInfo[0] as $mask => $disp) {
                    $startup = 0;
                    for ($i = 0; $i < $n; $i++) {
                        $bit = (1 << $i);
                        if (($mask & $bit) !== 0 && ($initialMask & $bit) === 0) {
                            $startup += $sortedUnits[$i]['startup_cost'];
                        }
                    }
                    $dp[0][$mask] = $disp['cost'] + $startup;
                    $parent[0][$mask] = $initialMask;
                }
            } else {
                $prevStates = $dp[$t - 1];
                if (empty($prevStates)) {
                    return [
                        'status' => 'INFEASIBLE',
                        'causing_hour' => $t,
                        'total_cost' => 0,
                        'hourly_schedule' => [],
                        'certificate' => null,
                    ];
                }

                foreach ($dispatchInfo[$t] as $mask => $disp) {
                    $bestCost = PHP_INT_MAX;
                    $bestPrev = null;

                    foreach ($prevStates as $prevMask => $prevCost) {
                        $startup = 0;
                        for ($i = 0; $i < $n; $i++) {
                            $bit = (1 << $i);
                            if (($mask & $bit) !== 0 && ($prevMask & $bit) === 0) {
                                $startup += $sortedUnits[$i]['startup_cost'];
                            }
                        }

                        $candidateCost = $prevCost + $disp['cost'] + $startup;
                        if ($candidateCost < $bestCost) {
                            $bestCost = $candidateCost;
                            $bestPrev = $prevMask;
                        }
                    }

                    if ($bestPrev !== null) {
                        $dp[$t][$mask] = $bestCost;
                        $parent[$t][$mask] = $bestPrev;
                    }
                }

                if (empty($dp[$t])) {
                    return [
                        'status' => 'INFEASIBLE',
                        'causing_hour' => $t,
                        'total_cost' => 0,
                        'hourly_schedule' => [],
                        'certificate' => null,
                    ];
                }
            }
        }

        // Find optimal final mask
        $lastHour = $h - 1;
        $bestFinalMask = null;
        $minTotalCost = PHP_INT_MAX;

        foreach ($dp[$lastHour] as $mask => $cost) {
            if ($cost < $minTotalCost) {
                $minTotalCost = $cost;
                $bestFinalMask = $mask;
            }
        }

        if ($bestFinalMask === null) {
            return [
                'status' => 'INFEASIBLE',
                'causing_hour' => $lastHour,
                'total_cost' => 0,
                'hourly_schedule' => [],
                'certificate' => null,
            ];
        }

        // Traceback schedule
        $scheduleReversed = [];
        $currMask = $bestFinalMask;

        for ($t = $lastHour; $t >= 0; $t--) {
            $prevMask = $parent[$t][$currMask];
            $disp = $dispatchInfo[$t][$currMask];

            $startup = 0;
            $onlineCodes = [];
            for ($i = 0; $i < $n; $i++) {
                $bit = (1 << $i);
                if (($currMask & $bit) !== 0) {
                    $onlineCodes[] = $sortedUnits[$i]['code'];
                    if (($prevMask & $bit) === 0) {
                        $startup += $sortedUnits[$i]['startup_cost'];
                    }
                }
            }

            $scheduleReversed[] = [
                'hour' => $t,
                'demand' => $hourlyDemands[$t],
                'online_units' => $onlineCodes,
                'dispatches' => $disp['dispatches'],
                'dispatch_cost' => $disp['cost'],
                'startup_cost' => $startup,
                'total_cost' => $disp['cost'] + $startup,
            ];

            $currMask = $prevMask;
        }

        $schedule = array_reverse($scheduleReversed);

        $certificate = [
            'total_cost' => $minTotalCost,
            'unit_count' => $n,
            'hours' => $h,
            'schedule_hash' => hash('sha256', json_encode($schedule, JSON_THROW_ON_ERROR)),
        ];

        $result = [
            'status' => 'OPTIMAL',
            'causing_hour' => null,
            'total_cost' => $minTotalCost,
            'hourly_schedule' => $schedule,
            'certificate' => $certificate,
        ];

        UnitCommitmentVerifier::verify($units, $hourlyDemands, $initialOnlineCodes, $result);

        return $result;
    }
}
