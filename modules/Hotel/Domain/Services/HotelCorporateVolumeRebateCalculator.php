<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelCorporateVolumeRebateCalculator
{
    /**
     * Menghitung insentif potongan tahunan (Volume Rebate / Kickback) bagi korporat yang melampaui target room nights tahunan.
     *
     * @return array{corporate_id: string, total_room_nights_annual: int, rebate_rate_bps: int, rebate_amount_idr: int, reward_tier: string}
     */
    public function computeAnnualRebate(string $corporateId, int $totalRoomNights, int $totalSpendIdr): array
    {
        if ($totalRoomNights < 0 || $totalSpendIdr < 0) {
            throw new InvalidArgumentException('Jumlah malam dan nilai transaksi tidak boleh negatif.');
        }

        $rebateBps = 0;
        $tier = 'NONE';

        if ($totalRoomNights >= 500) {
            $rebateBps = 500; // 5.0%
            $tier = 'DIAMOND_ENTERPRISE';
        } elseif ($totalRoomNights >= 250) {
            $rebateBps = 350; // 3.5%
            $tier = 'PLATINUM_PREFERRED';
        } elseif ($totalRoomNights >= 100) {
            $rebateBps = 200; // 2.0%
            $tier = 'GOLD_PARTNER';
        }

        $rebateAmount = (int) round(($totalSpendIdr * $rebateBps) / 10000);

        return [
            'corporate_id' => $corporateId,
            'total_room_nights_annual' => $totalRoomNights,
            'rebate_rate_bps' => $rebateBps,
            'rebate_amount_idr' => $rebateAmount,
            'reward_tier' => $tier,
        ];
    }
}
