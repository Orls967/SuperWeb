<?php

declare(strict_types=1);

namespace Modules\AutoDex\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoDex\Domain\Models\Wishlist;
use Modules\Shared\Application\BaseAction;

class AddCarToWishlistAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): Wishlist
    {
        return DB::transaction(function () use ($params) {
            $userId = (int) $params['user_id'];
            $carId = (string) $params['car_id'];
            $priority = (int) ($params['priority'] ?? 1);
            $notes = (string) ($params['notes'] ?? '');

            Car::findOrFail($carId);

            $wishlist = Wishlist::updateOrCreate(
                [
                    'user_id' => $userId,
                    'car_id' => $carId,
                ],
                [
                    'priority' => $priority,
                    'notes' => $notes,
                ]
            );

            $this->audit(
                action: 'autodex_car_wishlisted',
                auditable: $wishlist,
                newValues: ['user_id' => $userId, 'car_id' => $carId, 'priority' => $priority],
                impactType: 'customer_engagement',
            );

            return $wishlist;
        });
    }
}
