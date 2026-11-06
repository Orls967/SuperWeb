<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Algoritma E13: Pelepasan Beban Microgrid Berbobot Prioritas (0/1 Knapsack).
 * Menyelesaikan alokasi kapasitas pembangkitan terbatas saat transisi island mode,
 * memastikan beban kritis (Rumah Sakit, Data Center) dipertahankan deterministik.
 */
final class MicrogridLoadSheddingKnapsack
{
    /**
     * @param  int  $availableGenerationKw  Kapasitas total pembangkitan pulau dalam kW
     * @param  list<array{id: int, name: string, tier: string, priority_rank: int, required_kw: int}>  $feeders
     */
    public function solve(int $availableGenerationKw, array $feeders): MicrogridLoadSheddingResult
    {
        if ($availableGenerationKw < 0) {
            throw new InvalidArgumentException('Kapasitas pembangkitan tidak boleh negatif.');
        }

        if (empty($feeders)) {
            $payload = "LOAD-SHEDDING|CAP:{$availableGenerationKw}|SERVED:0|SHED:0|SCORE:0";

            return new MicrogridLoadSheddingResult(
                availableGenerationKw: $availableGenerationKw,
                totalServedKw: 0,
                totalShedKw: 0,
                totalUtilityScore: 0,
                retainedFeeders: [],
                shedFeeders: [],
                certificateHash: hash('sha256', $payload),
                canonicalPayload: $payload
            );
        }

        // Hitung bobot utilitas untuk setiap feeder berdasarkan tier dan rank
        $items = [];
        foreach ($feeders as $f) {
            $tierMultiplier = match ($f['tier']) {
                'TIER_1_CRITICAL' => 100_000,
                'TIER_2_ESSENTIAL' => 10_000,
                'TIER_3_COMMERCIAL' => 1_000,
                default => 100,
            };
            $rankBonus = max(1, 100 - $f['priority_rank']);
            $utilityScore = $tierMultiplier * $rankBonus;

            $items[] = [
                'id' => (int) $f['id'],
                'name' => (string) $f['name'],
                'tier' => (string) $f['tier'],
                'priority_rank' => (int) $f['priority_rank'],
                'weight' => (int) $f['required_kw'],
                'value' => (int) $utilityScore,
            ];
        }

        $n = count($items);
        $w = $availableGenerationKw;

        // DP 0/1 Knapsack dengan memori hemat dan rekonstruksi solusi
        // dp[i][j] menyatakan nilai maksimum menggunakan subset item 0..i-1 dengan kapasitas j kW
        $dp = array_fill(0, $n + 1, array_fill(0, $w + 1, 0));

        for ($i = 1; $i <= $n; $i++) {
            $itemWeight = $items[$i - 1]['weight'];
            $itemValue = $items[$i - 1]['value'];

            for ($j = 0; $j <= $w; $j++) {
                if ($itemWeight <= $j) {
                    $dp[$i][$j] = max(
                        $dp[$i - 1][$j],
                        $dp[$i - 1][$j - $itemWeight] + $itemValue
                    );
                } else {
                    $dp[$i][$j] = $dp[$i - 1][$j];
                }
            }
        }

        // Lakukan penelusuran balik (backtracking) untuk menemukan item yang dipilih
        $retainedFeeders = [];
        $shedFeeders = [];
        $remCap = $w;

        for ($i = $n; $i >= 1; $i--) {
            if ($dp[$i][$remCap] !== $dp[$i - 1][$remCap]) {
                // Item ke-(i-1) terpilih (dipertahankan)
                $retainedFeeders[] = $items[$i - 1];
                $remCap -= $items[$i - 1]['weight'];
            } else {
                // Feeder diputus (shed)
                $shedFeeders[] = $items[$i - 1];
            }
        }

        // Urutkan kembali berdasarkan prioritas rank
        usort($retainedFeeders, fn ($a, $b) => $a['priority_rank'] <=> $b['priority_rank']);
        usort($shedFeeders, fn ($a, $b) => $a['priority_rank'] <=> $b['priority_rank']);

        $totalServedKw = array_sum(array_column($retainedFeeders, 'weight'));
        $totalShedKw = array_sum(array_column($shedFeeders, 'weight'));
        $totalScore = $dp[$n][$w];

        $retainedIds = implode(',', array_column($retainedFeeders, 'id'));
        $shedIds = implode(',', array_column($shedFeeders, 'id'));

        $canonicalPayload = sprintf(
            'LOAD-SHEDDING|CAP:%d|SERVED:%d|SHED:%d|SCORE:%d|RETAINED:[%s]|SHED:[%s]',
            $availableGenerationKw,
            $totalServedKw,
            $totalShedKw,
            $totalScore,
            $retainedIds,
            $shedIds
        );

        $certificateHash = hash('sha256', $canonicalPayload);

        return new MicrogridLoadSheddingResult(
            availableGenerationKw: $availableGenerationKw,
            totalServedKw: $totalServedKw,
            totalShedKw: $totalShedKw,
            totalUtilityScore: $totalScore,
            retainedFeeders: $retainedFeeders,
            shedFeeders: $shedFeeders,
            certificateHash: $certificateHash,
            canonicalPayload: $canonicalPayload
        );
    }
}
