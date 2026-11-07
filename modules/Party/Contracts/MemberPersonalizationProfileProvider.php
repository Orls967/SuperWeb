<?php

declare(strict_types=1);

namespace Modules\Party\Contracts;

/**
 * G7-K25: Kontrak Aditif Profil Personalisasi & Preferensi Anggota VIP On-Site.
 */
interface MemberPersonalizationProfileProvider
{
    /**
     * Dapatkan profil preferensi minuman/makanan favorit dan metrik loyalitas anggota.
     *
     * @return array{
     *   user_id: int,
     *   tier_code: string,
     *   favorite_beverage_category: string,
     *   average_spend_per_night_idr: int,
     *   loyalty_points_balance: int,
     *   opt_in_contextual_promo: bool
     * }
     */
    public function getMemberPreferences(int $userId): array;
}
