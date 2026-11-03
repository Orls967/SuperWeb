<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for Battery Arbitrage schedules and certificates.
 *
 * Verifies that:
 * - 0 <= kw <= max_kw for every scheduled hour
 * - 0 <= soc <= capacity_kwh throughout the trace
 * - Final SoC strictly equals Initial SoC (cyclic boundary)
 * - Transitions match actions (charge increases SoC, discharge decreases SoC)
 * - Cash flows match TOU tariff and discharge efficiency formula
 * - Recalculated total profit identically matches claimed total profit
 */
final class BatteryArbitrageVerifier
{
    /**
     * @param array{
     *     total_profit: int,
     *     initial_soc_kwh: int,
     *     final_soc_kwh: int,
     *     capacity_kwh: int,
     *     max_kw: int,
     *     efficiency_bps: int,
     *     schedule: list<array{
     *         hour: int,
     *         action: string,
     *         kw: int,
     *         kwh: int,
     *         price: int,
     *         start_soc_kwh: int,
     *         end_soc_kwh: int,
     *         cash_flow: int
     *     }>,
     *     soc_trace: list<int>,
     *     certificate?: array<string, mixed>
     * } $result
     *
     * @throws UnexpectedValueException
     */
    public static function verify(array $result): bool
    {
        if (! isset($result['total_profit'], $result['initial_soc_kwh'], $result['final_soc_kwh'], $result['capacity_kwh'], $result['max_kw'], $result['efficiency_bps'], $result['schedule'], $result['soc_trace'])) {
            throw new UnexpectedValueException('Missing required fields in battery arbitrage result');
        }

        $capacity = $result['capacity_kwh'];
        $maxKw = $result['max_kw'];
        $effBps = $result['efficiency_bps'];
        $initSoc = $result['initial_soc_kwh'];
        $finalSoc = $result['final_soc_kwh'];
        $totalProfit = $result['total_profit'];
        $schedule = $result['schedule'];
        $socTrace = $result['soc_trace'];

        if ($capacity <= 0) {
            throw new UnexpectedValueException("Invalid battery capacity: {$capacity}");
        }
        if ($maxKw <= 0) {
            throw new UnexpectedValueException("Invalid max power: {$maxKw}");
        }
        if ($effBps <= 0 || $effBps > 10000) {
            throw new UnexpectedValueException("Invalid efficiency bps: {$effBps}");
        }
        if ($initSoc !== $finalSoc) {
            throw new UnexpectedValueException("Cyclic condition violated: initial SoC ({$initSoc}) != final SoC ({$finalSoc})");
        }
        if ($initSoc < 0 || $initSoc > $capacity) {
            throw new UnexpectedValueException("Initial SoC out of bounds: {$initSoc}");
        }

        $hours = count($schedule);
        if (count($socTrace) !== $hours + 1) {
            throw new UnexpectedValueException('SoC trace length does not match schedule hours + 1');
        }
        if ($socTrace[0] !== $initSoc) {
            throw new UnexpectedValueException("SoC trace start {$socTrace[0]} does not match initial SoC {$initSoc}");
        }

        $runningSoc = $initSoc;
        $runningProfit = 0;

        foreach ($schedule as $idx => $row) {
            if ($row['hour'] !== $idx) {
                throw new UnexpectedValueException("Hour index mismatch at index {$idx}: got {$row['hour']}");
            }
            if ($row['kw'] < 0 || $row['kw'] > $maxKw) {
                throw new UnexpectedValueException("Power rate {$row['kw']} out of bounds [0, {$maxKw}] at hour {$idx}");
            }
            if ($row['kwh'] !== $row['kw']) {
                throw new UnexpectedValueException("kWh {$row['kwh']} does not match 1-hour kW {$row['kw']} at hour {$idx}");
            }
            if ($row['start_soc_kwh'] !== $runningSoc) {
                throw new UnexpectedValueException("Continuity broken at hour {$idx}: start SoC {$row['start_soc_kwh']} != running SoC {$runningSoc}");
            }

            $action = $row['action'];
            $kw = $row['kw'];
            $price = $row['price'];

            if ($action === 'idle') {
                if ($kw !== 0) {
                    throw new UnexpectedValueException("Idle action with non-zero kw {$kw} at hour {$idx}");
                }
                if ($row['cash_flow'] !== 0) {
                    throw new UnexpectedValueException("Idle action with non-zero cash flow {$row['cash_flow']} at hour {$idx}");
                }
                $expectedEndSoc = $runningSoc;
            } elseif ($action === 'charge') {
                if ($kw <= 0) {
                    throw new UnexpectedValueException("Charge action with non-positive kw {$kw} at hour {$idx}");
                }
                $expectedCost = -($kw * $price);
                if ($row['cash_flow'] !== $expectedCost) {
                    throw new UnexpectedValueException("Charge cash flow mismatch at hour {$idx}: expected {$expectedCost}, got {$row['cash_flow']}");
                }
                $expectedEndSoc = $runningSoc + $kw;
            } elseif ($action === 'discharge') {
                if ($kw <= 0) {
                    throw new UnexpectedValueException("Discharge action with non-positive kw {$kw} at hour {$idx}");
                }
                $expectedRev = intdiv($kw * $price * $effBps + 5000, 10000);
                if ($row['cash_flow'] !== $expectedRev) {
                    throw new UnexpectedValueException("Discharge cash flow mismatch at hour {$idx}: expected {$expectedRev}, got {$row['cash_flow']}");
                }
                $expectedEndSoc = $runningSoc - $kw;
            } else {
                throw new UnexpectedValueException("Unknown action '{$action}' at hour {$idx}");
            }

            if ($expectedEndSoc < 0 || $expectedEndSoc > $capacity) {
                throw new UnexpectedValueException("End SoC {$expectedEndSoc} out of bounds [0, {$capacity}] at hour {$idx}");
            }
            if ($row['end_soc_kwh'] !== $expectedEndSoc) {
                throw new UnexpectedValueException("Row end SoC mismatch at hour {$idx}: expected {$expectedEndSoc}, got {$row['end_soc_kwh']}");
            }
            if ($socTrace[$idx + 1] !== $expectedEndSoc) {
                throw new UnexpectedValueException("Trace end SoC mismatch at hour {$idx}: expected {$expectedEndSoc}, got {$socTrace[$idx + 1]}");
            }

            $runningSoc = $expectedEndSoc;
            $runningProfit += $row['cash_flow'];
        }

        if ($runningSoc !== $finalSoc) {
            throw new UnexpectedValueException("Final trace SoC {$runningSoc} does not match final SoC {$finalSoc}");
        }
        if ($runningProfit !== $totalProfit) {
            throw new UnexpectedValueException("Total profit mismatch: recalculated {$runningProfit} != claimed {$totalProfit}");
        }

        return true;
    }
}
