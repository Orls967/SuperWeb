<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use InvalidArgumentException;

/**
 * Mesin aturan kelayakan putaway fisik (suhu, hazmat, berat rak bawah, dan konsolidasi SKU).
 */
final class PutawayRuleEngine
{
    /**
     * @param  array{
     *     requires_cold_chain: bool,
     *     is_hazmat: bool,
     *     unit_weight_g: int,
     *     product_id: int
     * }  $item
     * @param  list<array{
     *     bin_id: string,
     *     zone_temp: string,
     *     allows_hazmat: bool,
     *     rack_level: int,
     *     free_capacity: int,
     *     existing_product_ids: list<int>
     * }>  $bins
     * @return list<array{bin_id: string, score: int, reasons: list<string>}>
     */
    public function rankEligibleBins(array $item, array $bins): array
    {
        if ((int) $item['unit_weight_g'] < 0) {
            throw new InvalidArgumentException('Berat unit tidak boleh negatif.');
        }

        $isHeavy = (int) $item['unit_weight_g'] >= 10000;
        $eligible = [];

        foreach ($bins as $bin) {
            if ((int) $bin['free_capacity'] <= 0) {
                continue;
            }
            if ($item['requires_cold_chain'] && $bin['zone_temp'] !== 'cold') {
                continue;
            }
            if ($item['is_hazmat'] && ! $bin['allows_hazmat']) {
                continue;
            }
            if ($isHeavy && (int) $bin['rack_level'] > 2) {
                continue;
            }

            $score = 100;
            $reasons = ['base_eligible'];

            if (in_array((int) $item['product_id'], $bin['existing_product_ids'], true)) {
                $score += 40;
                $reasons[] = 'same_sku_consolidation';
            }
            if ((int) $bin['rack_level'] === 1) {
                $score += 15;
                $reasons[] = 'ground_level_ergonomic';
            }

            $eligible[] = [
                'bin_id' => (string) $bin['bin_id'],
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($eligible, static fn (array $a, array $b): int => [$b['score'], $a['bin_id']] <=> [$a['score'], $b['bin_id']]);

        return $eligible;
    }
}
