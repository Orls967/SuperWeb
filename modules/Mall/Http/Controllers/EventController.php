<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\CreateEventBookingAction;
use Modules\Mall\Application\Queries\EventCalendarQuery;
use Modules\Mall\Domain\Enums\EventType;
use Modules\Mall\Domain\Models\EventSpace;

class EventController extends Controller
{
    public function index(Request $request, EventCalendarQuery $query): View
    {
        $month = $request->query('month', date('Y-m'));
        $data = $query->get($month);

        return view('mall::events.index', [
            'spaces' => $data['spaces'],
            'bookings' => $data['bookings'],
            'monthYear' => $data['month_year'],
            'totalRevenue' => $data['total_revenue'],
            'currentMonth' => $month,
        ]);
    }

    public function store(Request $request, CreateEventBookingAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'event_space_id' => ['required', 'exists:mall_event_spaces,id'],
            'event_name' => ['required', 'string', 'max:150'],
            'event_type' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'booth_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $space = EventSpace::findOrFail($validated['event_space_id']);
            $type = EventType::from($validated['event_type']);

            $booking = $action->execute(
                space: $space,
                customer: $request->user(),
                eventName: $validated['event_name'],
                eventType: $type,
                startDate: $validated['start_date'],
                endDate: $validated['end_date'],
                boothCount: (int) ($validated['booth_count'] ?? 0),
                notes: $validated['notes'] ?? null
            );

            return back()->with('success', "Pemesanan area {$space->name} berhasil diajukan (Nomor: {$booking->booking_number}, Total: Rp ".number_format($booking->total_amount).').');
        } catch (\Throwable $e) {
            return back()->withErrors(['event_error' => $e->getMessage()]);
        }
    }
}
