<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Venue\Application\Actions\ForfeitTableNoShowDepositAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Application\Actions\SettleTableBillAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

class VenueTableController extends Controller
{
    public function index(Request $request): View
    {
        $events = EntertainmentEvent::where('status', '!=', 'cancelled')->get();
        $bookings = EntertainmentTableBooking::with('event')
            ->orderByDesc('id')
            ->paginate(15);

        $tables = [
            ['table_number' => 'V01', 'minimum_spend_idr' => 5_000_000, 'status' => 'available'],
            ['table_number' => 'V02', 'minimum_spend_idr' => 5_000_000, 'status' => 'reserved'],
            ['table_number' => 'V03', 'minimum_spend_idr' => 7_500_000, 'status' => 'available'],
            ['table_number' => 'V04', 'minimum_spend_idr' => 7_500_000, 'status' => 'occupied'],
            ['table_number' => 'V05', 'minimum_spend_idr' => 10_000_000, 'status' => 'available'],
            ['table_number' => 'V06', 'minimum_spend_idr' => 15_000_000, 'status' => 'reserved'],
        ];

        return view('venue::tables.index', [
            'tables' => $tables,
            'bookings' => $bookings,
            'events' => $events,
        ]);
    }

    public function reserve(
        Request $request,
        ReserveTableWithMinimumSpendDepositAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'event_id' => 'required|exists:entertainment_events,id',
            'table_number' => 'required|string|max:20',
            'minimum_spend_idr' => 'required|integer|min:100000',
            'deposit_amount_idr' => 'required|integer|min:100000',
        ]);

        $userId = (int) (Auth::id() ?? 1);
        $idempotencyKey = "ven:tbl:{$validated['event_id']}:{$userId}:{$validated['table_number']}";

        $result = $action->handle(
            eventId: (int) $validated['event_id'],
            userId: $userId,
            tableNumber: $validated['table_number'],
            minimumSpendIdr: (int) $validated['minimum_spend_idr'],
            depositAmountIdr: (int) $validated['deposit_amount_idr'],
            idempotencyKey: $idempotencyKey
        );

        return redirect()->route('venue.tables.index')
            ->with('success', "Reservasi meja #{$validated['table_number']} berhasil dibuat (Booking: {$result['booking_code']})");
    }

    public function settle(
        Request $request,
        EntertainmentTableBooking $booking,
        SettleTableBillAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'actual_consumption_idr' => 'required|integer|min:0',
        ]);

        $result = $action->execute(
            booking: $booking,
            actualConsumptionIdr: (int) $validated['actual_consumption_idr'],
            idempotencyKey: "ven:settle:tbl_bk_{$booking->id}"
        );

        return redirect()->route('venue.tables.index')
            ->with('success', 'Penyelesaian tagihan meja selesai. Settlement: Rp '.number_format($result['settlement']['total_bill_idr'], 0, ',', '.'));
    }

    public function forfeit(
        EntertainmentTableBooking $booking,
        ForfeitTableNoShowDepositAction $action
    ): RedirectResponse {
        $result = $action->execute(
            booking: $booking,
            reason: 'Tamu tidak hadir melewati batas toleransi no-show (120 menit)',
            idempotencyKey: "ven:forfeit:tbl_bk_{$booking->id}"
        );

        return redirect()->route('venue.tables.index')
            ->with('success', 'Deposit no-show meja hangus 50% (Rp '.number_format($result['forfeited_revenue_idr'], 0, ',', '.').') dan 50% dikembalikan.');
    }
}
