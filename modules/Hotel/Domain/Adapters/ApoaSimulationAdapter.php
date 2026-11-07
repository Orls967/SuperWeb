<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Carbon\CarbonImmutable;

/**
 * Adaptor Simulasi S1 Gateway Pelaporan Orang Asing (APOA) Imigrasi RI (UU 6/2011).
 */
final class ApoaSimulationAdapter
{
    /**
     * @param list<array{apoa_report_id: int, passport_blind_index: string, nationality: string}> $reports
     * @return array{batch_id: string, acknowledged: bool, simulated_level: string, timestamp: string}
     */
    public static function submitBatch(array $reports): array
    {
        $batchId = 'APOA-BATCH-' . date('YmdHis') . '-' . count($reports);

        return [
            'batch_id' => $batchId,
            'acknowledged' => true,
            'simulated_level' => 'S1',
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
