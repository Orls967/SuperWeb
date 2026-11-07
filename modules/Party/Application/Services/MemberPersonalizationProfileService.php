<?php

declare(strict_types=1);

namespace Modules\Party\Application\Services;

use Modules\Party\Contracts\MemberPersonalizationProfileProvider;

final class MemberPersonalizationProfileService implements MemberPersonalizationProfileProvider
{
    public function getMemberPreferences(int $userId): array
    {
        return [
            'user_id' => $userId,
            'tier_code' => 'VIP_PLATINUM',
            'favorite_beverage_category' => 'CHAMPAGNE_AND_SPIRITS',
            'average_spend_per_night_idr' => 7_500_000,
            'loyalty_points_balance' => 4500,
            'opt_in_contextual_promo' => true,
        ];
    }
}
