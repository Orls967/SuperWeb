<?php

declare(strict_types=1);

namespace Modules\AutoDex\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\AutoDex\Domain\Models\Wishlist;
use Modules\Shared\Application\BaseAction;

class RemoveCarFromWishlistAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): bool
    {
        return DB::transaction(function () use ($params) {
            $userId = (int) $params['user_id'];
            $carId = (string) $params['car_id'];

            $wishlist = Wishlist::where('user_id', $userId)
                ->where('car_id', $carId)
                ->first();

            if ($wishlist === null) {
                return false;
            }

            $this->audit(
                action: 'autodex_car_unwishlisted',
                auditable: $wishlist,
                newValues: ['user_id' => $userId, 'car_id' => $carId],
                impactType: 'customer_engagement',
            );

            return (bool) $wishlist->delete();
        });
    }
}
