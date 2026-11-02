<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Production verifier for UnitCommitment certificates.
 * Checks capacity feasibility, dispatch merit order, startup cost transitions,
 * and for <= 4 units compares against an independent brute-force oracle.
 */
final class UnitCommitmentVerifier
{
    /**
     * @param  list<array{code: string, min_mw: int, max_mw: int, marginal_cost: int, startup_cost: int}>  $units
     * @param  list<int>  $hourlyDemands
     * @param  list<string>  $initialOnlineCodes
     * @param  array<string, mixed>  $result
     *
     * @throws UnexpectedValueException
     */
    public static function verify(array $units, array $hourlyDemands, array $initialOnlineCodes, array $result): bool
    {
        if ($result['status'] === 'INFEASIBLE') {
            $h = $result['causing_hour'] ?? 0;
            $demand = $hourlyDemands[$h] ?? 0;
            $totalMax = 0;
            foreach ($units as $u) {
                $totalMax += $u['max_mw'];
            }
            if ($demand <= $totalMax) {
                // If demand is less than totalMax, check if any combination could satisfy it
                $canSatisfy = false;
                $numStates = 1 << count($units);
                for ($m = 0; $m < $numStates; $m++) {
                    $active = [];
                    for ($i = 0; $i < count($units); $i++) {
                        if (($m & (1 << $i)) !== 0) {
                            $active[] = $units[$i];
                        }
                    }
                    if (MeritOrderDispatch::dispatch($active, $demand) !== null) {
                        $canSatisfy = true;
                        break;
                    }
                }
                if ($canSatisfy) {
                    throw new UnexpectedValueException("Marked infeasible at hour {$h}, but a valid dispatch exists.");
                }
            }

            return true;
        }

        if ($result['status'] !== 'OPTIMAL') {
            throw new UnexpectedValueException("Unknown status: '{$result['status']}'.");
        }

        $schedule = $result['hourly_schedule'] ?? [];
        if (count($schedule) !== count($hourlyDemands)) {
            throw new UnexpectedValueException('Schedule length does not match demand hours.');
        }

        $unitsByCode = [];
        foreach ($units as $u) {
            $unitsByCode[$u['code']] = $u;
        }

        $prevOnline = array_flip($initialOnlineCodes);
        $recalculatedTotalCost = 0;

        foreach ($schedule as $t => $hourPlan) {
            $demand = $hourlyDemands[$t];
            if ($hourPlan['demand'] !== $demand) {
                throw new UnexpectedValueException("Demand mismatch at hour {$t}.");
            }

            $onlineCodes = $hourPlan['online_units'];
            $onlineUnits = [];
            foreach ($onlineCodes as $code) {
                if (! isset($unitsByCode[$code])) {
                    throw new UnexpectedValueException("Unknown unit code '{$code}' at hour {$t}.");
                }
                $onlineUnits[] = $unitsByCode[$code];
            }

            // Check dispatches
            $dispatches = $hourPlan['dispatches'];
            $sumDispatch = 0;
            foreach ($onlineCodes as $code) {
                $mw = $dispatches[$code] ?? 0;
                $u = $unitsByCode[$code];
                if ($mw < $u['min_mw'] || $mw > $u['max_mw']) {
                    throw new UnexpectedValueException("Dispatch for unit '{$code}' out of bounds at hour {$t}: {$mw}.");
                }
                $sumDispatch += $mw;
            }

            if ($sumDispatch !== $demand) {
                throw new UnexpectedValueException("Dispatches sum {$sumDispatch} does not meet demand {$demand} at hour {$t}.");
            }

            // Check Merit Order optimality for this active set
            $merit = MeritOrderDispatch::dispatch($onlineUnits, $demand);
            if ($merit === null || $merit['cost'] !== $hourPlan['dispatch_cost']) {
                throw new UnexpectedValueException("Dispatch cost at hour {$t} not optimal for online units.");
            }

            // Check startup costs
            $expectedStartup = 0;
            foreach ($onlineCodes as $code) {
                if (! isset($prevOnline[$code])) {
                    $expectedStartup += $unitsByCode[$code]['startup_cost'];
                }
            }

            if ($hourPlan['startup_cost'] !== $expectedStartup) {
                throw new UnexpectedValueException("Startup cost mismatch at hour {$t}: expected {$expectedStartup}, got {$hourPlan['startup_cost']}.");
            }

            $hourTotal = $hourPlan['dispatch_cost'] + $hourPlan['startup_cost'];
            if ($hourPlan['total_cost'] !== $hourTotal) {
                throw new UnexpectedValueException("Hour total mismatch at hour {$t}.");
            }

            $recalculatedTotalCost += $hourTotal;
            $prevOnline = array_flip($onlineCodes);
        }

        if ($recalculatedTotalCost !== $result['total_cost']) {
            throw new UnexpectedValueException("Total cost mismatch: got {$result['total_cost']}, recalculated {$recalculatedTotalCost}.");
        }

        // For n <= 4 units and <= 5 hours: verify against exhaustive brute-force oracle
        if (count($units) <= 4 && count($hourlyDemands) <= 5) {
            $bruteForceMin = self::bruteForceOptimal($units, $hourlyDemands, $initialOnlineCodes);
            if ($result['total_cost'] !== $bruteForceMin) {
                throw new UnexpectedValueException("DP result ({$result['total_cost']}) does not match brute-force optimum ({$bruteForceMin}).");
            }
        }

        return true;
    }

    /**
     * Independent brute force oracle for <= 4 units.
     *
     * @param  list<array{code: string, min_mw: int, max_mw: int, marginal_cost: int, startup_cost: int}>  $units
     * @param  list<int>  $hourlyDemands
     * @param  list<string>  $initialOnlineCodes
     */
    public static function bruteForceOptimal(array $units, array $hourlyDemands, array $initialOnlineCodes): int
    {
        $n = count($units);
        $numStates = 1 << $n;
        $h = count($hourlyDemands);

        $initialMask = 0;
        foreach ($initialOnlineCodes as $code) {
            foreach ($units as $idx => $u) {
                if ($u['code'] === $code) {
                    $initialMask |= (1 << $idx);
                }
            }
        }

        $minCost = PHP_INT_MAX;

        // Recursive generator of all state sequences
        $explore = function (int $hour, int $prevMask, int $accumCost) use (&$explore, &$minCost, $units, $hourlyDemands, $h, $n, $numStates): void {
            if ($hour === $h) {
                if ($accumCost < $minCost) {
                    $minCost = $accumCost;
                }

                return;
            }

            $demand = $hourlyDemands[$hour];

            for ($mask = 0; $mask < $numStates; $mask++) {
                $active = [];
                for ($i = 0; $i < $n; $i++) {
                    if (($mask & (1 << $i)) !== 0) {
                        $active[] = $units[$i];
                    }
                }

                $disp = MeritOrderDispatch::dispatch($active, $demand);
                if ($disp === null) {
                    continue;
                }

                $startup = 0;
                for ($i = 0; $i < $n; $i++) {
                    $bit = (1 << $i);
                    if (($mask & $bit) !== 0 && ($prevMask & $bit) === 0) {
                        $startup += $units[$i]['startup_cost'];
                    }
                }

                $newCost = $accumCost + $disp['cost'] + $startup;
                if ($newCost >= $minCost) {
                    continue; // pruning
                }

                $explore($hour + 1, $mask, $newCost);
            }
        };

        $explore(0, $initialMask, 0);

        return $minCost;
    }
}
