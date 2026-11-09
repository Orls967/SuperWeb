<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class TableLockDurationEstimator
{
    /**
     * Estimates table lock time in seconds based on row count and algorithm.
     */
    public function estimateLockDuration(int $rowCount, string $algorithm): float
    {
        if ($algorithm === 'INSTANT') {
            return 0.05; // Metadata lock only (< 50ms)
        }

        if ($algorithm === 'INPLACE') {
            return 0.10; // Brief metadata lock at start and finish
        }

        // ALGORITHM=COPY: Table copy scales linearly at approx 20,000 rows/sec on SSD
        $copyRateRowsPerSec = 20000.0;

        return round(max(1.0, $rowCount / $copyRateRowsPerSec), 2);
    }
}
