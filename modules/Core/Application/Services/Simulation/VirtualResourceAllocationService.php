<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use InvalidArgumentException;

/**
 * Layanan kuota sumber daya komputasi virtual untuk sesi simulasi multi-tenant.
 */
final class VirtualResourceAllocationService
{
    /** @var array<string, array{max_concurrent_runs: int, max_days_per_run: int, max_memory_mb: int}> */
    private array $tenantQuotas = [
        'default' => [
            'max_concurrent_runs' => 3,
            'max_days_per_run' => 365,
            'max_memory_mb' => 512,
        ],
        'premium' => [
            'max_concurrent_runs' => 10,
            'max_days_per_run' => 1825,
            'max_memory_mb' => 2048,
        ],
    ];

    /**
     * @return array{max_concurrent_runs: int, max_days_per_run: int, max_memory_mb: int}
     */
    public function getQuota(string $tier = 'default'): array
    {
        return $this->tenantQuotas[$tier] ?? $this->tenantQuotas['default'];
    }

    public function validateRunRequest(int $requestedDays, string $tier = 'default'): bool
    {
        $quota = $this->getQuota($tier);
        if ($requestedDays > $quota['max_days_per_run']) {
            throw new InvalidArgumentException(
                "Jumlah hari yang diminta ({$requestedDays}) melebihi kuota tier [{$tier}] maksimum {$quota['max_days_per_run']} hari."
            );
        }

        return true;
    }
}
