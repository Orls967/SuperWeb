<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Battery Arbitrage Optimizer using Dynamic Programming across discrete State-of-Charge (SoC) levels.
 *
 * Maximizes net arbitrage revenue over T hours under TOU electricity pricing subject to:
 * - 0 <= kw <= max_kw (hourly charge/discharge power)
 * - min_soc <= soc <= max_soc (energy bounds in kWh)
 * - cyclic boundary condition: soc[T] == soc[0]
 * - discharge efficiency in basis points (10,000 bps = 100%, 9,000 bps = 90%)
 *
 * Complexity: O(T * (capacity / step) * (max_kw / step))
 */
final class BatteryArbitrage
{
    /**
     * @param  int  $capacityKwh  Total battery storage capacity (kWh > 0)
     * @param  int  $maxKw  Maximum charge/discharge power rate (kW > 0)
     * @param  list<int>  $prices  Hourly electricity prices in IDR/kWh
     * @param  int  $efficiencyBps  Efficiency in basis points, applied on discharge (1..10000)
     * @param  int|null  $initialSocKwh  Starting and ending state of charge (defaults to minSocKwh or 0)
     * @param  int|null  $minSocKwh  Minimum allowable SoC (defaults to 0)
     * @param  int|null  $maxSocKwh  Maximum allowable SoC (defaults to capacityKwh)
     * @param  int  $socStep  Discretization step in kWh (default: 1)
     * @return array{
     *     total_profit: int,
     *     initial_soc_kwh: int,
     *     final_soc_kwh: int,
     *     capacity_kwh: int,
     *     max_kw: int,
     *     efficiency_bps: int,
     *     schedule: list<array{
     *         hour: int,
     *         action: 'charge'|'discharge'|'idle',
     *         kw: int,
     *         kwh: int,
     *         price: int,
     *         start_soc_kwh: int,
     *         end_soc_kwh: int,
     *         cash_flow: int
     *     }>,
     *     soc_trace: list<int>,
     *     certificate: array{
     *         capacity_kwh: int,
     *         max_kw: int,
     *         efficiency_bps: int,
     *         initial_soc_kwh: int,
     *         final_soc_kwh: int,
     *         total_profit: int,
     *         hours_count: int,
     *         schedule: list<array{
     *             hour: int,
     *             action: 'charge'|'discharge'|'idle',
     *             kw: int,
     *             kwh: int,
     *             price: int,
     *             start_soc_kwh: int,
     *             end_soc_kwh: int,
     *             cash_flow: int
     *         }>
     *     }
     * }
     */
    public static function optimize(
        int $capacityKwh,
        int $maxKw,
        array $prices,
        int $efficiencyBps = 9000,
        ?int $initialSocKwh = null,
        ?int $minSocKwh = null,
        ?int $maxSocKwh = null,
        int $socStep = 1,
    ): array {
        if ($capacityKwh <= 0) {
            throw new InvalidArgumentException("Battery capacity must be positive, got: {$capacityKwh}");
        }
        if ($maxKw <= 0) {
            throw new InvalidArgumentException("Max power must be positive, got: {$maxKw}");
        }
        if ($efficiencyBps <= 0 || $efficiencyBps > 10000) {
            throw new InvalidArgumentException("Efficiency bps must be between 1 and 10000, got: {$efficiencyBps}");
        }
        if ($socStep <= 0) {
            throw new InvalidArgumentException("SoC step must be positive, got: {$socStep}");
        }
        if ($prices === []) {
            throw new InvalidArgumentException('Prices array cannot be empty');
        }

        $minSoc = $minSocKwh ?? 0;
        $maxSoc = $maxSocKwh ?? $capacityKwh;

        if ($minSoc < 0 || $minSoc > $capacityKwh) {
            throw new InvalidArgumentException("Min SoC must be within [0, {$capacityKwh}], got: {$minSoc}");
        }
        if ($maxSoc < $minSoc || $maxSoc > $capacityKwh) {
            throw new InvalidArgumentException("Max SoC must be within [{$minSoc}, {$capacityKwh}], got: {$maxSoc}");
        }

        $initSoc = $initialSocKwh ?? $minSoc;
        if ($initSoc < $minSoc || $initSoc > $maxSoc) {
            throw new InvalidArgumentException("Initial SoC must be within [{$minSoc}, {$maxSoc}], got: {$initSoc}");
        }
        if (($initSoc - $minSoc) % $socStep !== 0) {
            throw new InvalidArgumentException("Initial SoC {$initSoc} must align with socStep {$socStep}");
        }

        $hours = count($prices);

        // dp[t][soc] = max cumulative profit
        // parent[t][soc] = ['prev_s' => int, 'action' => string, 'kw' => int, 'cash_flow' => int]
        $dp = [];
        $parent = [];

        $dp[0][$initSoc] = 0;

        for ($t = 0; $t < $hours; $t++) {
            $price = $prices[$t];
            $currentStates = $dp[$t] ?? [];
            if ($currentStates === []) {
                break;
            }

            $dp[$t + 1] = [];
            $parent[$t + 1] = [];

            foreach ($currentStates as $s => $currentProfit) {
                // Option 1: Idle
                if (! isset($dp[$t + 1][$s]) || $currentProfit > $dp[$t + 1][$s]) {
                    $dp[$t + 1][$s] = $currentProfit;
                    $parent[$t + 1][$s] = [
                        'prev_s' => $s,
                        'action' => 'idle',
                        'kw' => 0,
                        'cash_flow' => 0,
                    ];
                }

                // Option 2: Charge
                $maxCharge = min($maxKw, $maxSoc - $s);
                for ($kw = $socStep; $kw <= $maxCharge; $kw += $socStep) {
                    $nextS = $s + $kw;
                    $cost = -($kw * $price);
                    $newProfit = $currentProfit + $cost;

                    if (! isset($dp[$t + 1][$nextS]) || $newProfit > $dp[$t + 1][$nextS]) {
                        $dp[$t + 1][$nextS] = $newProfit;
                        $parent[$t + 1][$nextS] = [
                            'prev_s' => $s,
                            'action' => 'charge',
                            'kw' => $kw,
                            'cash_flow' => $cost,
                        ];
                    }
                }

                // Option 3: Discharge
                $maxDischarge = min($maxKw, $s - $minSoc);
                for ($kw = $socStep; $kw <= $maxDischarge; $kw += $socStep) {
                    $nextS = $s - $kw;
                    $rev = intdiv($kw * $price * $efficiencyBps + 5000, 10000);
                    $newProfit = $currentProfit + $rev;

                    if (! isset($dp[$t + 1][$nextS]) || $newProfit > $dp[$t + 1][$nextS]) {
                        $dp[$t + 1][$nextS] = $newProfit;
                        $parent[$t + 1][$nextS] = [
                            'prev_s' => $s,
                            'action' => 'discharge',
                            'kw' => $kw,
                            'cash_flow' => $rev,
                        ];
                    }
                }
            }
        }

        if (! isset($dp[$hours][$initSoc])) {
            throw new UnexpectedValueException("Infeasible battery arbitrage schedule: cannot return to initial SoC {$initSoc}");
        }

        $totalProfit = $dp[$hours][$initSoc];

        // Backtrack optimal schedule
        $schedule = [];
        $currS = $initSoc;

        for ($t = $hours; $t >= 1; $t--) {
            if (! isset($parent[$t][$currS])) {
                throw new UnexpectedValueException("Broken backtrack pointer at hour {$t} for SoC {$currS}");
            }
            $p = $parent[$t][$currS];
            $prevS = $p['prev_s'];
            $schedule[$t - 1] = [
                'hour' => $t - 1,
                'action' => $p['action'],
                'kw' => $p['kw'],
                'kwh' => $p['kw'],
                'price' => $prices[$t - 1],
                'start_soc_kwh' => $prevS,
                'end_soc_kwh' => $currS,
                'cash_flow' => $p['cash_flow'],
            ];
            $currS = $prevS;
        }

        ksort($schedule);
        /** @var list<array{hour: int, action: 'charge'|'discharge'|'idle', kw: int, kwh: int, price: int, start_soc_kwh: int, end_soc_kwh: int, cash_flow: int}> $orderedSchedule */
        $orderedSchedule = array_values($schedule);

        $socTrace = [$initSoc];
        foreach ($orderedSchedule as $item) {
            $socTrace[] = $item['end_soc_kwh'];
        }

        $certificate = [
            'capacity_kwh' => $capacityKwh,
            'max_kw' => $maxKw,
            'efficiency_bps' => $efficiencyBps,
            'initial_soc_kwh' => $initSoc,
            'final_soc_kwh' => $initSoc,
            'total_profit' => $totalProfit,
            'hours_count' => $hours,
            'schedule' => $orderedSchedule,
        ];

        $result = [
            'total_profit' => $totalProfit,
            'initial_soc_kwh' => $initSoc,
            'final_soc_kwh' => $initSoc,
            'capacity_kwh' => $capacityKwh,
            'max_kw' => $maxKw,
            'efficiency_bps' => $efficiencyBps,
            'schedule' => $orderedSchedule,
            'soc_trace' => $socTrace,
            'certificate' => $certificate,
        ];

        BatteryArbitrageVerifier::verify($result);

        return $result;
    }
}
