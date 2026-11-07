<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelCorporateRateAgreementService
{
    /**
     * @var array<string, array{base_discount_bps: int, max_rooms_per_night: int, blackout_dates: list<string>}>
     */
    private const TIER_RULES = [
        'TIER_1_BLUE_CHIP' => [
            'base_discount_bps' => 3000, // 30.0%
            'max_rooms_per_night' => 20,
            'blackout_dates' => ['12-31', '01-01'],
        ],
        'TIER_2_NATIONAL' => [
            'base_discount_bps' => 2000, // 20.0%
            'max_rooms_per_night' => 10,
            'blackout_dates' => ['12-24', '12-25', '12-31', '01-01'],
        ],
        'TIER_3_STARTUP' => [
            'base_discount_bps' => 1500, // 15.0%
            'max_rooms_per_night' => 5,
            'blackout_dates' => ['12-20', '12-21', '12-22', '12-23', '12-24', '12-25', '12-31', '01-01'],
        ],
    ];

    /**
     * Menghitung tarif terdiskon untuk pemesanan kamar korporat dengan validasi tanggal blackout.
     *
     * @return array{corporate_id: string, published_rate_idr: int, negotiated_rate_idr: int, discount_idr: int, is_blackout_applied: bool}
     */
    public function calculateCorporateNightRate(
        string $corporateAccountId,
        int $publishedRateIdr,
        string $stayDate,
        string $tierCategory = 'TIER_1_BLUE_CHIP'
    ): array {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);

        if ($publishedRateIdr <= 0) {
            throw new InvalidArgumentException('Tarif publik kamar harus lebih besar dari nol.');
        }

        $rules = self::TIER_RULES[$tierCategory] ?? self::TIER_RULES['TIER_2_NATIONAL'];
        $dateMonthDay = substr($stayDate, 5); // mm-dd

        $isBlackout = in_array($dateMonthDay, $rules['blackout_dates'], true);

        // Jika blackout date, diskon ditiadakan (kembali ke published rate)
        if ($isBlackout) {
            return [
                'corporate_id' => $corp->id,
                'published_rate_idr' => $publishedRateIdr,
                'negotiated_rate_idr' => $publishedRateIdr,
                'discount_idr' => 0,
                'is_blackout_applied' => true,
            ];
        }

        $discountIdr = (int) round(($publishedRateIdr * $rules['base_discount_bps']) / 10000);
        $negotiatedRate = max(0, $publishedRateIdr - $discountIdr);

        return [
            'corporate_id' => $corp->id,
            'published_rate_idr' => $publishedRateIdr,
            'negotiated_rate_idr' => $negotiatedRate,
            'discount_idr' => $discountIdr,
            'is_blackout_applied' => false,
        ];
    }
}
