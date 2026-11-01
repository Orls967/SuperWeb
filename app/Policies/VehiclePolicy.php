<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Modules\Core\Domain\Models\Vehicle;

class VehiclePolicy
{
    public function attach(User $user, Vehicle $vehicle): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $vehicle->user_id === $user->id;
    }
}
