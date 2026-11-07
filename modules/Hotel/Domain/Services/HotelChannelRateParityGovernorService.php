<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Pengawasan Paritas Tarif Saluran Distribusi OTA & Mitigasi Penalti.
 */
final class HotelChannelRateParityGovernorService
{
    /**
     * @param  array<string, int>  $channelPricesIdr
     * @return array{is_compliant: bool, violations: list<array<string, mixed>>, action_required: string}
     */
    public function auditParity(int $officialDirectRateIdr, array $channelPricesIdr): array
    {
        $violations = [];
        $isCompliant = true;

        foreach ($channelPricesIdr as $channelName => $channelPrice) {
            // Toleransi perbedaan harga maksimal 1%
            $minAllowed = (int) round($officialDirectRateIdr * 0.99);
            if ($channelPrice < $minAllowed) {
                $isCompliant = false;
                $discountBps = (int) round((($officialDirectRateIdr - $channelPrice) / $officialDirectRateIdr) * 10000);
                $violations[] = [
                    'channel' => $channelName,
                    'price_idr' => $channelPrice,
                    'deviation_bps' => $discountBps,
                    'severity' => $discountBps > 500 ? 'CRITICAL' : 'WARNING',
                ];
            }
        }

        return [
            'is_compliant' => $isCompliant,
            'violations' => $violations,
            'action_required' => $isCompliant ? 'NONE' : 'SEND_PARITY_NOTICE',
        ];
    }
}
