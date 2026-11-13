<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Menghitung kelonggaran waktu (slack minutes) terhadap cutoff kurir untuk penjadwalan wave robotika (J2).
 */
final class WaveCutoffSlackCalculator
{
    public const int CRITICAL_SLACK_MIN = 30;

    public const int TIGHT_SLACK_MIN = 90;

    /**
     * @param  list<array{wave_id:int, carrier_code:string, cutoff_hhmm:int, estimated_pick_min:int}>  $waves
     * @return list<array{wave_id:int, carrier_code:string, slack_minutes:int, urgency:string, at_risk:bool}>
     */
    public function evaluate(array $waves, int $currentHhmm): array
    {
        $currentMinutes = self::hhmmToMinutes($currentHhmm);
        $results = [];

        foreach ($waves as $wave) {
            $cutoffHhmm = (int) $wave['cutoff_hhmm'];
            $cutoffMinutes = self::hhmmToMinutes($cutoffHhmm);
            $pickMin = max(0, (int) $wave['estimated_pick_min']);

            $slack = $cutoffMinutes - $currentMinutes - $pickMin;
            $urgency = match (true) {
                $slack <= self::CRITICAL_SLACK_MIN => 'CRITICAL',
                $slack <= self::TIGHT_SLACK_MIN => 'TIGHT',
                default => 'NORMAL',
            };

            $results[] = [
                'wave_id' => (int) $wave['wave_id'],
                'carrier_code' => (string) $wave['carrier_code'],
                'slack_minutes' => $slack,
                'urgency' => $urgency,
                'at_risk' => $slack < 0,
            ];
        }

        usort($results, static fn (array $a, array $b): int => $a['slack_minutes'] <=> $b['slack_minutes']);

        return $results;
    }

    public static function hhmmToMinutes(int $hhmm): int
    {
        if ($hhmm < 0 || $hhmm > 2359) {
            throw new InvalidArgumentException("Format HHMM tidak valid: {$hhmm}.");
        }

        $hours = intdiv($hhmm, 100);
        $minutes = $hhmm % 100;
        if ($hours > 23 || $minutes > 59) {
            throw new InvalidArgumentException("Jam/menit HHMM tidak valid: {$hhmm}.");
        }

        return ($hours * 60) + $minutes;
    }
}
