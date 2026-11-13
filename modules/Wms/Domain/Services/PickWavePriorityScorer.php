<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Penilai skor prioritas order untuk pembentukan wave picking outbound.
 */
final class PickWavePriorityScorer
{
    /**
     * @param  list<array{
     *     order_id: string,
     *     minutes_to_cutoff: int,
     *     is_express: bool,
     *     line_count: int,
     *     age_hours: int
     * }>  $orders
     * @return list<array{order_id: string, priority_score: int, tier: string}>
     */
    public function scoreAndRank(array $orders): array
    {
        $scored = [];

        foreach ($orders as $order) {
            $score = 0;

            $cutoff = (int) $order['minutes_to_cutoff'];
            if ($cutoff <= 60) {
                $score += 500;
            } elseif ($cutoff <= 180) {
                $score += 250;
            } else {
                $score += 50;
            }

            if ($order['is_express']) {
                $score += 300;
            }

            $score += min(200, max(0, (int) $order['age_hours']) * 5);

            if ((int) $order['line_count'] === 1) {
                $score += 40; // single-line fast-track
            }

            $tier = match (true) {
                $score >= 700 => 'P1_CRITICAL',
                $score >= 400 => 'P2_HIGH',
                default => 'P3_STANDARD',
            };

            $scored[] = [
                'order_id' => (string) $order['order_id'],
                'priority_score' => $score,
                'tier' => $tier,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => [$b['priority_score'], $a['order_id']] <=> [$a['priority_score'], $b['order_id']]);

        return $scored;
    }
}
