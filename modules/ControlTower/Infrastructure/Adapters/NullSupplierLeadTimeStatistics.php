<?php

declare(strict_types=1);

namespace Modules\ControlTower\Infrastructure\Adapters;

use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;

/**
 * Fallback default ketika implementasi Procurement\Contracts\SupplierLeadTimeStatistics belum terikat di container.
 */
final class NullSupplierLeadTimeStatistics implements SupplierLeadTimeStatistics
{
    public function forSupplierAndItem(string $supplierRef, string $itemCode): array
    {
        return [
            'mean_days' => 0,
            'sigma_days' => 0,
            'samples' => 0,
            'on_time_permil' => 0,
        ];
    }

    public function primarySupplierForItem(string $itemCode): ?string
    {
        return null;
    }

    public function allStatistics(): array
    {
        return [];
    }

    public function monthlyHistory(string $supplierRef, int $months, string $asOfDate): array
    {
        return [];
    }
}
