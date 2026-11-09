<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Backfill;

use Modules\Platform\Domain\Models\DataBackfill;

final class BackfillThroughputTelemetry
{
    /**
     * Calculates processing throughput metrics and estimated completion time (ETA).
     *
     * @return array{current_tps: float, progress_pct: float, remaining_records: int, eta_minutes: float}
     */
    public function calculateTelemetry(DataBackfill $backfill, float $elapsedMinutes): array
    {
        $processed = $backfill->cursor_pointer;
        $total = max(1, $backfill->total_records);
        $remaining = max(0, $total - $processed);

        $progressPct = round(($processed / $total) * 100.0, 2);

        $recordsPerMinute = $elapsedMinutes > 0.0 ? ($processed / $elapsedMinutes) : 0.0;
        $currentTps = round($recordsPerMinute / 60.0, 2);

        $etaMinutes = $recordsPerMinute > 0.0 ? round($remaining / $recordsPerMinute, 1) : 0.0;

        return [
            'current_tps' => $currentTps,
            'progress_pct' => $progressPct,
            'remaining_records' => $remaining,
            'eta_minutes' => $etaMinutes,
        ];
    }
}
