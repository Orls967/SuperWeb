<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Actions\BookPassengerTicketAction;
use Modules\Aviation\Application\Actions\HoldSeatInventoryAction;
use Modules\Aviation\Application\Actions\ProcessFareRefundAction;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Http\Requests\ConfirmBookingRequest;
use Modules\Aviation\Http\Requests\HoldSeatRequest;
use Modules\Aviation\Http\Requests\RefundTicketRequest;

class AviationBookingController extends Controller
{
    public function __construct(
        private readonly HoldSeatInventoryAction $holdAction,
        private readonly BookPassengerTicketAction $bookAction,
        private readonly ProcessFareRefundAction $refundAction
    ) {
    }

    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $query = AviationTicket::with(['flight.route', 'flight.origin', 'flight.destination'])->orderByDesc('created_at');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $tickets = $query->paginate(15)->withQueryString();
        $flights = AviationFlight::whereIn('status', ['planned', 'slot_secured'])->limit(20)->get();

        return view('aviation::bookings.index', [
            'tickets' => $tickets,
            'flights' => $flights,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function holdSeat(HoldSeatRequest $request): JsonResponse
    {
        return $this->hold($request);
    }

    public function confirmBooking(ConfirmBookingRequest $request): JsonResponse
    {
        return $this->confirm($request);
    }

    public function refundTicket(AviationTicket $ticket, RefundTicketRequest $request): JsonResponse
    {
        return $this->refund($ticket, $request);
    }

    public function hold(HoldSeatRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        $flightId = (int) $request->input('flight_id');
        $cabin = (string) $request->input('cabin');
        $seats = (int) $request->input('seats');

        $hold = $this->holdAction->execute($flightId, $cabin, $seats, $idempotencyKey);

        return response()->json([
            'success' => true,
            'message' => 'Kursi berhasil di-hold sementara.',
            'hold_id' => $hold->id,
            'fare_minor' => $hold->fare_minor,
            'currency' => $hold->currency,
            'expires_at' => $hold->expires_at->toIso8601String(),
        ], 200);
    }

    public function confirm(ConfirmBookingRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        $holdId = (int) $request->input('hold_id');
        $names = (array) $request->input('passenger_names');
        $partyIds = (array) ($request->input('passenger_party_ids') ?? []);

        $tickets = $this->bookAction->execute($holdId, $partyIds, $names, $idempotencyKey);

        return response()->json([
            'success' => true,
            'message' => 'Tiket berhasil diterbitkan dan pembayaran dicatat.',
            'ticket_ids' => array_map(fn($t) => $t->id, $tickets),
            'ticket_numbers' => array_map(fn($t) => $t->ticket_number, $tickets),
        ], 200);
    }

    public function refund(AviationTicket $ticket, RefundTicketRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        $reason = (string) $request->input('reason');
        $hours = $request->input('hours_before_flight') !== null ? (int) $request->input('hours_before_flight') : null;

        $refund = $this->refundAction->execute($ticket->id, $reason, $idempotencyKey, $hours);

        return response()->json([
            'success' => true,
            'message' => 'Pengembalian dana tiket berhasil diproses.',
            'refund_id' => $refund->id,
            'net_refund_minor' => $refund->net_refund_minor,
            'currency' => $refund->currency,
        ], 200);
    }
}

