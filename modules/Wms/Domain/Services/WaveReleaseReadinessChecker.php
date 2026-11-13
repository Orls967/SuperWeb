<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Pemeriksa kesiapan pra-rilis wave picking (stok, reservasi, kapasitas dock, cut-off kurir).
 */
final class WaveReleaseReadinessChecker
{
    /**
     * @param  list<array{product_id: int, requested_qty: int, available_qty: int}>  $lines
     * @return array{
     *     ready: bool,
     *     blocking_reasons: list<string>,
     *     shortage_products: list<int>
     * }
     */
    public function check(array $lines, bool $dockAssigned, bool $withinCarrierCutoff): array
    {
        $reasons = [];
        $shortages = [];

        if ($lines === []) {
            $reasons[] = 'WAVE_EMPTY_LINES';
        }

        foreach ($lines as $line) {
            if ((int) $line['available_qty'] < (int) $line['requested_qty']) {
                $shortages[] = (int) $line['product_id'];
            }
        }

        if ($shortages !== []) {
            $reasons[] = 'INSUFFICIENT_AVAILABLE_STOCK';
        }
        if (! $dockAssigned) {
            $reasons[] = 'DOCK_BAY_NOT_ASSIGNED';
        }
        if (! $withinCarrierCutoff) {
            $reasons[] = 'CARRIER_CUTOFF_PASSED';
        }

        return [
            'ready' => $reasons === [],
            'blocking_reasons' => $reasons,
            'shortage_products' => $shortages,
        ];
    }
}
