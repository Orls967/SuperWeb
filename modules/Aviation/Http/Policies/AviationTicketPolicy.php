<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Policies;

use App\Models\User;
use Modules\Aviation\Domain\Models\AviationTicket;

class AviationTicketPolicy
{
    public function view(User $user, AviationTicket $ticket): bool
    {
        // Admin, customer care, ops controller punya akses menyeluruh
        if ($user->hasRole(['admin', 'customer_care', 'ops_controller', 'superadmin'])) {
            return true;
        }

        // Penumpang hanya boleh melihat tiket miliknya sendiri (Anti-IDOR)
        return (int) $user->id === (int) $ticket->passenger_party_id;
    }

    public function refund(User $user, AviationTicket $ticket): bool
    {
        return $user->hasRole(['admin', 'customer_care', 'superadmin']);
    }
}

