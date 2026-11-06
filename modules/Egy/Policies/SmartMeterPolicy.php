<?php

declare(strict_types=1);

namespace Modules\Egy\Policies;

use App\Models\User;
use Modules\Egy\Domain\Models\SmartMeter;

class SmartMeterPolicy
{
    public function view(User $user, SmartMeter $meter): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('energy_billing') || $user->hasRole('grid_operator') || $user->hasRole('energy_auditor')) {
            return true;
        }

        if ($user->hasRole('customer')) {
            return (string) $meter->customer_party_id === (string) $user->id
                || (string) $meter->customer_party_id === (string) ($user->party_id ?? '');
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('energy_billing') || $user->hasRole('grid_operator');
    }

    public function update(User $user, SmartMeter $meter): bool
    {
        return $user->hasRole('admin') || $user->hasRole('energy_billing') || $user->hasRole('grid_operator');
    }
}
