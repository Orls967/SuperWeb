<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelTravelPassTierBenefitService
{
    /**
     * @return array{
     *     tier: string,
     *     dining_discount_pct: int,
     *     spa_discount_pct: int,
     *     points_earning_multiplier: float,
     *     complimentary_breakfast_pax: int,
     *     room_upgrade_priority: string,
     *     guaranteed_late_checkout: string,
     *     lounge_access_granted: bool,
     *     dedicated_checkin_counter: bool,
     *     annual_gift_points_limit: int,
     *     benefits_list: list<string>
     * }
     */
    public function getTierEntitlements(string $travelPassId): array
    {
        $pass = HtlTravelPass::findOrFail($travelPassId);
        $tier = $pass->tier;

        return match ($tier) {
            'BLACK' => [
                'tier' => 'BLACK',
                'dining_discount_pct' => 25,
                'spa_discount_pct' => 25,
                'points_earning_multiplier' => 2.0, // 100% bonus
                'complimentary_breakfast_pax' => 2,
                'room_upgrade_priority' => 'SUITE_OR_HIGHEST_AVAILABLE',
                'guaranteed_late_checkout' => '18:00 WITA',
                'lounge_access_granted' => true,
                'dedicated_checkin_counter' => true,
                'annual_gift_points_limit' => 200000,
                'benefits_list' => [
                    'Private Head Butler service sepanjang menginap',
                    'Akses gratis Executive Lounge untuk 2 orang dewasa',
                    'Sarapan gratis prasmanan setiap hari untuk 2 orang',
                    'Jaminan upgrade ke tipe Suite terbaik saat check-in',
                    'Check-out istimewa hingga pukul 18:00',
                    'Jaminan ketersediaan kamar 24 jam sebelum kedatangan',
                    'Penjemputan bandara dengan limusin mewah',
                    'Bonus perolehan poin ganda (2.0x)',
                ],
            ],
            'PLATINUM' => [
                'tier' => 'PLATINUM',
                'dining_discount_pct' => 20,
                'spa_discount_pct' => 20,
                'points_earning_multiplier' => 1.5, // 50% bonus
                'complimentary_breakfast_pax' => 2,
                'room_upgrade_priority' => 'EXECUTIVE_FLOOR_DELUXE',
                'guaranteed_late_checkout' => '16:00 WITA',
                'lounge_access_granted' => true,
                'dedicated_checkin_counter' => true,
                'annual_gift_points_limit' => 150000,
                'benefits_list' => [
                    'Akses Executive Lounge untuk pemegang kartu',
                    'Sarapan gratis prasmanan untuk 2 orang',
                    'Prioritas upgrade satu tingkat kamar',
                    'Check-out santai hingga pukul 16:00',
                    'Bonus perolehan poin 1.5x',
                    'Welcome amenity artisan signature setiap menginap',
                ],
            ],
            'GOLD' => [
                'tier' => 'GOLD',
                'dining_discount_pct' => 15,
                'spa_discount_pct' => 10,
                'points_earning_multiplier' => 1.25, // 25% bonus
                'complimentary_breakfast_pax' => 1,
                'room_upgrade_priority' => 'PREFERRED_VIEW_ENHANCED',
                'guaranteed_late_checkout' => '14:00 WITA',
                'lounge_access_granted' => false,
                'dedicated_checkin_counter' => true,
                'annual_gift_points_limit' => 100000,
                'benefits_list' => [
                    'Diskon 15% untuk seluruh restoran & outlet F&B hotel',
                    'Sarapan gratis untuk 1 orang',
                    'Check-out fleksibel hingga pukul 14:00',
                    'Bonus perolehan poin 1.25x',
                    'Jalur prioritas front desk check-in',
                ],
            ],
            default => [
                'tier' => 'SILVER',
                'dining_discount_pct' => 10,
                'spa_discount_pct' => 5,
                'points_earning_multiplier' => 1.0,
                'complimentary_breakfast_pax' => 0,
                'room_upgrade_priority' => 'STANDARD_BASED_ON_AVAILABILITY',
                'guaranteed_late_checkout' => '12:00 WITA',
                'lounge_access_granted' => false,
                'dedicated_checkin_counter' => false,
                'annual_gift_points_limit' => 50000,
                'benefits_list' => [
                    'Diskon 10% F&B dining di seluruh outlet',
                    'Internet Wi-Fi berkecepatan tinggi tanpa batas',
                    'Akses ke penawaran eksklusif flash sale poin bulanan',
                ],
            ],
        };
    }
}
