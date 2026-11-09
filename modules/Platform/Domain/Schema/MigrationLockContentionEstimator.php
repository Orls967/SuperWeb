<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class MigrationLockContentionEstimator
{
    /**
     * @return array{
     *     contention_risk: string,
     *     estimated_lock_wait_seconds: float,
     *     requires_read_only_window: bool,
     *     advisory: string
     * }
     */
    public function estimateContention(
        int $estimatedTableRows,
        float $qpsRate,
        string $lockType = 'metadata_exclusive'
    ): array {
        $waitFactor = match ($lockType) {
            'metadata_exclusive' => 0.05,
            'table_exclusive' => 0.25,
            'shared_read' => 0.01,
            default => 0.0,
        };

        $estimatedWait = round(($estimatedTableRows / 50000.0) * $waitFactor * (1.0 + ($qpsRate / 100.0)), 2);

        $risk = match (true) {
            $estimatedWait >= 10.0 || $qpsRate > 500 => 'critical',
            $estimatedWait >= 3.0 || $qpsRate > 150 => 'high',
            $estimatedWait >= 1.0 => 'medium',
            default => 'low',
        };

        $requiresMaintenance = ($risk === 'critical' || $risk === 'high') && $lockType === 'table_exclusive';

        $advisory = match ($risk) {
            'critical' => 'Severe lock contention hazard: DDL will block connection pool. Schedule during low-traffic window and set innodb_lock_wait_timeout.',
            'high' => 'Elevated lock contention: Monitor MySQL performance schema for metadata lock waits.',
            'medium' => 'Moderate contention: Safe to run during off-peak hours.',
            default => 'Negligible lock contention: Safe for continuous online deployment.',
        };

        return [
            'contention_risk' => $risk,
            'estimated_lock_wait_seconds' => $estimatedWait,
            'requires_read_only_window' => $requiresMaintenance,
            'advisory' => $advisory,
        ];
    }
}
