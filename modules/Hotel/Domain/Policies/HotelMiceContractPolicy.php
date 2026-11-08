<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Policies;

use App\Models\User;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelMiceContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'sales_mgr', 'finance_mgr', 'front_desk']);
    }

    public function view(User $user, MiceContract $contract): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'sales_mgr', 'finance_mgr', 'front_desk']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'hotel_gm', 'sales_mgr']);
    }

    public function update(User $user, MiceContract $contract): bool
    {
        if ($contract->status === 'settled' || $contract->status === 'cancelled') {
            return false;
        }

        return $user->hasRole(['admin', 'hotel_gm', 'sales_mgr']);
    }

    public function approveMargin(User $user, MiceContract $contract): bool
    {
        return $user->hasRole(['admin', 'hotel_gm']);
    }

    public function cancel(User $user, MiceContract $contract): bool
    {
        return $user->hasRole(['admin', 'hotel_gm']);
    }
}
