<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use InvalidArgumentException;

/**
 * Utility Meter Reading Consumption Delta and Anomaly Analyzer.
 *
 * Computes interval consumption from sequential meter register readings,
 * handling register rollover (modulus), intentional meter resets, deduplication,
 * and detecting non-monotonic anomalies.
 *
 * Complexity: O(N log N) where N is number of readings (dominated by sorting).
 *
 * @author Google Deepmind & Antigravity
 */
final class MeterDelta
{
    /**
     * Calculate consumption deltas from a series of readings.
     *
     * @param  list<MeterReading|array{timestamp: int, register: int, isReset?: bool}>  $readings
     * @param  int|null  $modulus  Modulus for odometer rollover (e.g. 100,000 for 99,999 max register)
     *
     * @throws InvalidArgumentException
     */
    public static function calculate(array $readings, ?int $modulus = null): MeterDeltaResult
    {
        if ($modulus !== null && $modulus <= 0) {
            throw new InvalidArgumentException("Modulus must be strictly positive: {$modulus}");
        }

        // Normalize to MeterReading instances
        /** @var list<MeterReading> $normalized */
        $normalized = [];
        foreach ($readings as $r) {
            if ($r instanceof MeterReading) {
                $normalized[] = $r;
            } elseif (is_array($r)) {
                $normalized[] = new MeterReading(
                    timestamp: (int) $r['timestamp'],
                    register: (int) $r['register'],
                    isReset: (bool) ($r['isReset'] ?? false)
                );
            } else {
                throw new InvalidArgumentException('Readings must be instances of MeterReading or associative arrays');
            }
        }

        // Validate readings against modulus
        if ($modulus !== null) {
            foreach ($normalized as $r) {
                if ($r->register >= $modulus) {
                    throw new InvalidArgumentException("Register reading {$r->register} exceeds modulus {$modulus}");
                }
            }
        }

        // PR3 Determinism: Sort readings by timestamp ascending, then register ascending
        usort($normalized, function (MeterReading $a, MeterReading $b): int {
            if ($a->timestamp !== $b->timestamp) {
                return $a->timestamp <=> $b->timestamp;
            }

            return $a->register <=> $b->register;
        });

        // Deduplicate consecutive readings with identical timestamp
        /** @var list<MeterReading> $deduped */
        $deduped = [];
        foreach ($normalized as $r) {
            if (! empty($deduped) && end($deduped)->timestamp === $r->timestamp) {
                // Ignore duplicate reading at same timestamp
                continue;
            }
            $deduped[] = $r;
        }

        $count = count($deduped);
        if ($count < 2) {
            return new MeterDeltaResult(
                intervals: [],
                totalConsumption: 0,
                rolloverCount: 0,
                resetCount: 0,
                anomalies: [],
                hasAnomalies: false
            );
        }

        $intervals = [];
        $totalConsumption = 0;
        $rolloverCount = 0;
        $resetCount = 0;
        $anomalies = [];

        for ($i = 1; $i < $count; $i++) {
            $prev = $deduped[$i - 1];
            $curr = $deduped[$i];

            $prevReg = $prev->register;
            $currReg = $curr->register;
            $isReset = $curr->isReset;
            $isRollover = false;
            $isAnomaly = false;
            $consumption = 0;

            if ($currReg >= $prevReg) {
                $consumption = $currReg - $prevReg;
            } else {
                // Register decreased: currReg < prevReg
                if ($isReset) {
                    // Intentional reset / meter replacement: consumption measured from 0
                    $consumption = $currReg;
                    $resetCount++;
                } elseif ($modulus !== null) {
                    // Modulus rollover: e.g. 99,950 -> 30 with modulus 100,000 = (30 + 100,000) - 99,950 = 80
                    $consumption = ($currReg + $modulus) - $prevReg;
                    $isRollover = true;
                    $rolloverCount++;
                } else {
                    // Negative delta without reset and without modulus is an anomaly
                    $isAnomaly = true;
                    $consumption = 0;
                    $anomalies[] = "Negative reading drop from {$prevReg} to {$currReg} at timestamp {$curr->timestamp} without reset";
                }
            }

            $intervals[] = new MeterInterval(
                startTimestamp: $prev->timestamp,
                endTimestamp: $curr->timestamp,
                startRegister: $prevReg,
                endRegister: $currReg,
                consumption: $consumption,
                isRollover: $isRollover,
                isReset: $isReset,
                isAnomaly: $isAnomaly
            );

            if (! $isAnomaly) {
                $totalConsumption += $consumption;
            }
        }

        return new MeterDeltaResult(
            intervals: $intervals,
            totalConsumption: $totalConsumption,
            rolloverCount: $rolloverCount,
            resetCount: $resetCount,
            anomalies: $anomalies,
            hasAnomalies: ! empty($anomalies)
        );
    }
}
