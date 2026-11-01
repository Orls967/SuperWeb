<?php

declare(strict_types=1);

namespace Modules\AutoServe\Policies;

use App\Models\User;
use Modules\AutoServe\Domain\Models\Booking;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin() || $user->isMekanik() || $user->isAuditor()) {
            return true;
        }

        return $booking->customer_id === $user->id;
    }

    public function pay(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $booking->customer_id === $user->id;
    }

    public function refund(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }
}
