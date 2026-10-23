<?php

declare(strict_types=1);

namespace Modules\AutoDex\Contracts;

interface AutoDexWishlistInterface
{
    public function remove(int $userId, int $carId): void;
}
