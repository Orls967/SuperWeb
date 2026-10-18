<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

interface MultiRegionResilienceInterface
{
    /**
     * Get multi-region replication topology and health status.
     *
     * @return array<string, mixed>
     */
    public function getReplicationTopology(): array;

    /**
     * Validate failover parameters against RPO and RTO SLA thresholds.
     *
     * @return array{compliant: bool, rpo_seconds: int, rto_seconds: int, message: string}
     */
    public function evaluateSlaCompliance(int $rpoSeconds, int $rtoSeconds): array;
}
