<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use InvalidArgumentException;

/**
 * Simulator Match Factor (D93-2): MF = n_truk × t_muat / (n_shovel × t_siklus), rasional (pembilang/penyebut).
 * Rekomendasi: MF < 1 undertrucked (tambah truk), MF > 1 overtrucked (alihkan truk), = 1 seimbang.
 */
final class MatchFactorOptimizationService
{
    /**
     * @return array{numerator: int, denominator: int, mf_bps: int, state: string, optimal_trucks: int, recommendation: string}
     */
    public function simulate(int $trucks, int $shovels, int $loadMinutes, int $cycleMinutes): array
    {
        if ($shovels < 1 || $cycleMinutes < 1 || $loadMinutes < 1 || $trucks < 0) {
            throw new InvalidArgumentException('Simulasi MF membutuhkan shovel, waktu muat, dan siklus > 0.');
        }

        $num = $trucks * $loadMinutes;
        $den = $shovels * $cycleMinutes;
        $optimal = intdiv($den + $loadMinutes - 1, $loadMinutes);

        if ($num < $den) {
            $state = 'UNDERTRUCKED';
            $rec = 'Tambah '.($optimal - $trucks)." truk (target {$optimal}) agar shovel tidak menganggur.";
        } elseif ($num > $den) {
            $state = 'OVERTRUCKED';
            $rec = 'Alihkan '.($trucks - $optimal)." truk (target {$optimal}) agar antrean di shovel berkurang.";
        } else {
            $state = 'BALANCED';
            $rec = 'Armada seimbang.';
        }

        return [
            'numerator' => $num,
            'denominator' => $den,
            'mf_bps' => intdiv($num * 10000, $den),
            'state' => $state,
            'optimal_trucks' => $optimal,
            'recommendation' => $rec,
        ];
    }
}
