<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class GuestTicketPortalController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) Auth::id();

        $tickets = EntertainmentTicket::with(['event', 'tier'])
            ->where('buyer_user_id', $userId)
            ->orderByDesc('id')
            ->paginate(15);

        return view('venue::portal.index', [
            'tickets' => $tickets,
            'userId' => $userId,
        ]);
    }

    public function show(string $ticketUuid): View
    {
        $ticket = EntertainmentTicket::with(['event', 'tier'])
            ->where('ticket_uuid', $ticketUuid)
            ->firstOrFail();

        $currentUserId = (int) Auth::id();

        // Pemeriksaan IDOR ketat: Tamu hanya boleh mengakses tiket milik mereka sendiri
        if ((int) $ticket->buyer_user_id !== $currentUserId) {
            abort(403, 'Akses tiket ditolak: Anda bukan pemilik sah tiket ini (IDOR Guard).');
        }

        // Generate dynamic QR security verification payload
        $qrPayload = hash_hmac(
            'sha256',
            "TICKET:{$ticket->ticket_uuid}:{$ticket->ticket_number}:{$ticket->buyer_user_id}",
            config('app.key', 'venue-secret-salt')
        );

        return view('venue::portal.show', [
            'ticket' => $ticket,
            'qrPayload' => $qrPayload,
        ]);
    }
}
