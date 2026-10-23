<?php

declare(strict_types=1);

namespace Modules\AutoDex\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\AutoDex\Contracts\AutoDexWishlistInterface;

class AutoDexWishlistService implements AutoDexWishlistInterface
{
    public function remove(int $userId, int $carId): void
    {
        DB::table('dex_wishlists')
            ->where('user_id', $userId)
            ->where('car_id', $carId)
            ->delete();
    }
}
