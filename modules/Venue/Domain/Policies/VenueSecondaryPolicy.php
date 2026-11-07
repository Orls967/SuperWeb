<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Policies;

use App\Models\User;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\Models\TicketWaitlist;

final class VenueSecondaryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TicketResale $resale): bool
    {
        return true;
    }

    public function listTicket(User $user): bool
    {
        return true;
    }

    public function cancelListing(User $user, TicketResale $resale): bool
    {
        if ($resale->seller_user_id === $user->id) {
            return true;
        }

        return $this->isVenueStaff($user);
    }

    public function purchaseResale(User $user, TicketResale $resale): bool
    {
        // Penjual dilarang membeli tiketnya sendiri
        if ($resale->seller_user_id === $user->id) {
            return false;
        }

        return true;
    }

    public function manageBundles(User $user): bool
    {
        return $this->isVenueStaff($user);
    }

    public function claimWaitlist(User $user, TicketWaitlist $waitlist): bool
    {
        return $waitlist->user_id === $user->id;
    }

    public function viewAuditAndTax(User $user): bool
    {
        return $this->isVenueStaff($user);
    }

    private function isVenueStaff(User $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('super-admin')
            || $user->hasRole('venue-admin')
            || $user->hasRole('promoter')
            || $user->hasRole('ticketing-lead')
            || $user->hasRole('auditor');
    }
}
