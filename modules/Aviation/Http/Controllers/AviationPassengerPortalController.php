<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Domain\Models\AviationTicket;

class AviationPassengerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tickets = AviationTicket::with(['flight.route', 'flight.origin', 'flight.destination'])
            ->where('passenger_party_id', $user?->id)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('aviation::passenger.index', [
            'tickets' => $tickets,
        ]);
    }

    public function showTicket(AviationTicket $ticket, Request $request): View
    {
        return $this->ticket($ticket, $request);
    }

    public function ticket(AviationTicket $ticket, Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Akses ditolak.');
        }

        // Anti-IDOR: Penumpang hanya boleh melihat tiket miliknya sendiri
        if (! $user->hasAnyRbacRole(['admin', 'customer_care', 'ops_controller', 'superadmin'])) {
            if ((int) $user->id !== (int) $ticket->passenger_party_id) {
                abort(403, 'Anda tidak memiliki hak akses melihat tiket penumpang lain.');
            }
        }

        $ticket->load(['flight.route', 'flight.origin', 'flight.destination', 'refund']);

        return view('aviation::passenger.ticket', [
            'ticket' => $ticket,
        ]);
    }
}

