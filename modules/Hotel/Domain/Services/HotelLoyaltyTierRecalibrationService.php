<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HotelGuestIdentity;

class HotelLoyaltyTierRecalibrationService
{
    /**
     * Memindai seluruh profil tamu dan mengkalibrasi ulang status tier tahunan secara massal.
     *
     * @return array{total_guests_scanned: int, upgraded_count: int, maintained_count: int, downgraded_count: int, recalibrated_at: string}
     */
    public function batchRecalibrateTiers(): array
    {
        $guests = HotelGuestIdentity::all();

        $upgraded = 0;
        $maintained = 0;
        $downgraded = 0;

        foreach ($guests as $g) {
            $meta = $g->metadata ?? [];
            $nights = (int) ($meta['nights_this_year'] ?? 0);
            $currentTier = (string) ($meta['tier'] ?? 'SILVER');

            $newTier = 'SILVER';
            if ($nights >= 25) {
                $newTier = 'PLATINUM';
            } elseif ($nights >= 10) {
                $newTier = 'GOLD';
            }

            if ($newTier === $currentTier) {
                $maintained++;
            } elseif ($nights >= 10 && $newTier !== 'SILVER') {
                $upgraded++;
            } else {
                $downgraded++;
            }

            $meta['tier'] = $newTier;
            $g->metadata = $meta;
            $g->save();
        }

        return [
            'total_guests_scanned' => $guests->count(),
            'upgraded_count' => $upgraded,
            'maintained_count' => $maintained,
            'downgraded_count' => $downgraded,
            'recalibrated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
