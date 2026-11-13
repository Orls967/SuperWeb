<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use Carbon\CarbonImmutable;

/**
 * Penjaga masa simpan (shelf-life) produk untuk mencegah pengiriman barang kadaluarsa atau mendekati kadaluarsa.
 */
final class ShelfLifeGuard
{
    /**
     * @return array{status: string, remaining_days: int, is_pickable: bool}
     */
    public function inspect(CarbonImmutable $today, ?string $expiryDate, int $minSellableDays = 30): array
    {
        if ($expiryDate === null || trim($expiryDate) === '') {
            return [
                'status' => 'no_expiry',
                'remaining_days' => PHP_INT_MAX,
                'is_pickable' => true,
            ];
        }

        $expiry = CarbonImmutable::parse($expiryDate)->startOfDay();
        $remainingDays = (int) $today->startOfDay()->diffInDays($expiry, false);

        if ($remainingDays < 0) {
            return [
                'status' => 'expired',
                'remaining_days' => $remainingDays,
                'is_pickable' => false,
            ];
        }

        if ($remainingDays < $minSellableDays) {
            return [
                'status' => 'near_expiry',
                'remaining_days' => $remainingDays,
                'is_pickable' => false,
            ];
        }

        return [
            'status' => 'fresh',
            'remaining_days' => $remainingDays,
            'is_pickable' => true,
        ];
    }
}
