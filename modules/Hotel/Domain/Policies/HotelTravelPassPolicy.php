<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Policies;

use App\Models\User;
use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelTravelPassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hotel_admin', 'front_desk', 'finance']);
    }

    public function view(User $user, HtlTravelPass $pass): bool
    {
        if ($user->hasAnyRole(['admin', 'hotel_admin', 'front_desk'])) {
            return true;
        }

        return (string) $user->id === (string) $pass->user_id;
    }

    public function transferPoints(User $user, HtlTravelPass $pass): bool
    {
        if ($user->hasAnyRole(['admin', 'hotel_admin'])) {
            return true;
        }

        return (string) $user->id === (string) $pass->user_id && $pass->is_active;
    }

    public function auditBreakage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hotel_admin', 'finance']);
    }

    public function manageCampaigns(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'hotel_admin', 'sales_mgr']);
    }
}
