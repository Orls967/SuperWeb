<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Support\QuantityNormalizer;

/**
 * Layanan eksekusi blind-count & pemicu recount otomatis saat varians melebihi ambang bps.
 */
final class WmsCycleCountExecutionService
{
    /**
     * @param  list<array{bin_id: string, product_id: int, system_qty_base: int, first_count_base: int, second_count_base?: ?int}>  $lines
     * @return array{
     *     recount_required_count: int,
     *     accepted_count: int,
     *     decisions: list<array{bin_id: string, product_id: int, final_qty_base: int, status: string, variance_bps: int}>
     * }
     */
    public function evaluateBlindCounts(array $lines, int $recountThresholdBps = 200): array
    {
        $recounts = 0;
        $accepted = 0;
        $decisions = [];

        foreach ($lines as $line) {
            $sys = max(0, (int) $line['system_qty_base']);
            $first = max(0, (int) $line['first_count_base']);
            $second = isset($line['second_count_base']) ? max(0, (int) $line['second_count_base']) : null;

            $bps = QuantityNormalizer::varianceBps(max(1, $sys), $first);
            if (abs($bps) > $recountThresholdBps && $second === null) {
                $recounts++;
                $decisions[] = [
                    'bin_id' => (string) $line['bin_id'],
                    'product_id' => (int) $line['product_id'],
                    'final_qty_base' => $first,
                    'status' => 'recount_required',
                    'variance_bps' => $bps,
                ];

                continue;
            }

            $finalQty = $second ?? $first;
            $accepted++;
            $decisions[] = [
                'bin_id' => (string) $line['bin_id'],
                'product_id' => (int) $line['product_id'],
                'final_qty_base' => $finalQty,
                'status' => 'accepted',
                'variance_bps' => QuantityNormalizer::varianceBps(max(1, $sys), $finalQty),
            ];
        }

        return [
            'recount_required_count' => $recounts,
            'accepted_count' => $accepted,
            'decisions' => $decisions,
        ];
    }
}
