<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class HotelLoyaltyRewardRedemptionEngine
{
    /**
     * @var array<string, array{reward_name: string, required_points: int, monetary_value_idr: int}>
     */
    private const REWARDS_CATALOG = [
        'REWARD_FREE_NIGHT_DELUXE' => [
            'reward_name' => 'Free 1 Night Deluxe Room Stay',
            'required_points' => 15000,
            'monetary_value_idr' => 1850000,
        ],
        'REWARD_SPA_TREATMENT_60M' => [
            'reward_name' => '60-Minute Balinese Massage at Resort Spa',
            'required_points' => 6000,
            'monetary_value_idr' => 450000,
        ],
        'REWARD_AIRPORT_TRANSFER_VIP' => [
            'reward_name' => 'VIP Alphard One-Way Airport Transfer',
            'required_points' => 8000,
            'monetary_value_idr' => 950000,
        ],
        'REWARD_DINNER_BUFFET_TWO' => [
            'reward_name' => 'International Buffet Dinner for 2 Pax',
            'required_points' => 7500,
            'monetary_value_idr' => 850000,
        ],
    ];

    /**
     * Memproses penukaran poin loyalitas tamu untuk produk reward reward katalog.
     *
     * @return array{redemption_code: string, reward_code: string, reward_name: string, points_deducted: int, monetary_value_idr: int, voucher_expires_at: string}
     */
    public function redeemReward(string $guestId, string $rewardCode, int $currentPointsBalance): array
    {
        if (! isset(self::REWARDS_CATALOG[$rewardCode])) {
            throw new InvalidArgumentException("Kode reward tidak ditemukan dalam katalog: {$rewardCode}");
        }

        $reward = self::REWARDS_CATALOG[$rewardCode];
        if ($currentPointsBalance < $reward['required_points']) {
            throw new InvalidArgumentException("Saldo poin tidak mencukupi. Diminta: {$reward['required_points']}, Tersedia: {$currentPointsBalance}");
        }

        $redemptionCode = 'RDM-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $expiry = CarbonImmutable::now()->addMonths(6)->toDateString();

        return [
            'redemption_code' => $redemptionCode,
            'reward_code' => $rewardCode,
            'reward_name' => $reward['reward_name'],
            'points_deducted' => $reward['required_points'],
            'monetary_value_idr' => $reward['monetary_value_idr'],
            'voucher_expires_at' => $expiry,
        ];
    }
}
