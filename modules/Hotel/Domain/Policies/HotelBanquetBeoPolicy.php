<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Policies;

use App\Models\User;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;

final class HotelBanquetBeoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'chef_banquet', 'fb_director', 'banquet_captain']);
    }

    public function view(User $user, HtlBanquetOrder $order): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'chef_banquet', 'fb_director', 'banquet_captain']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'fb_director', 'sales_mgr']);
    }

    public function updateBom(User $user, HtlBanquetOrder $order): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'chef_banquet', 'fb_director']);
    }

    public function finalizeProduction(User $user, HtlBanquetOrder $order): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'chef_banquet']);
    }
}
