<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Sanctum
{
    /**
     * Set the current user for the application with the given abilities.
     *
     * @param  array<string>  $abilities
     */
    public static function actingAs(User $user, array $abilities = ['*'], string $guard = 'sanctum'): User
    {
        if (method_exists($user, 'withAccessTokenAbilities')) {
            $user->withAccessTokenAbilities($abilities);
        }

        Auth::guard('web')->setUser($user);
        Auth::guard('sanctum')->setUser($user);

        return $user;
    }
}
