<?php

declare(strict_types=1);

namespace Modules\Proptech\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;

class FlexSpaceController extends Controller
{
    public function index(Request $request): View
    {
        $spaces = FlexSpace::with('zone')->latest()->get();
        $bookings = FlexBooking::with('space.zone')->latest()->take(20)->get();

        $totalSpaces = $spaces->count();
        $activeBookingsCount = FlexBooking::whereIn('status', ['HELD', 'CONFIRMED', 'CHECKED_IN'])->count();
        $totalRevenueIdr = (int) FlexBooking::where('status', 'CHECKED_IN')->sum('total_price_idr');
        $totalDepositHeldIdr = (int) FlexBooking::where('status', 'HELD')->sum('deposit_amount_idr');

        return view('proptech::flex.index', compact(
            'spaces',
            'bookings',
            'totalSpaces',
            'activeBookingsCount',
            'totalRevenueIdr',
            'totalDepositHeldIdr'
        ));
    }

    public function store(
        Request $request,
        FlexSpaceService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'flex_space_id' => 'required|exists:prp_flex_spaces,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $space = FlexSpace::findOrFail((int) $validated['flex_space_id']);
        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);
        $userId = (int) ($request->user()?->id ?? 1);

        try {
            $booking = $service->createBooking($space, $userId, $startTime, $endTime);

            return redirect()->route('proptech.flex.index')
                ->with('success', "Booking {$booking->booking_code} untuk {$space->name} berhasil dibuat dengan deposit hold.");
        } catch (\Throwable $e) {
            return redirect()->route('proptech.flex.index')
                ->with('error', $e->getMessage());
        }
    }

    public function checkIn(
        Request $request,
        FlexBooking $booking,
        FlexSpaceService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'passport_token' => 'nullable|string|max:128',
        ]);

        $token = ! empty($validated['passport_token']) ? $validated['passport_token'] : 'PASSPORT-'.strtoupper(bin2hex(random_bytes(8)));

        try {
            $service->checkInWithPassport($booking, $token);

            return redirect()->route('proptech.flex.index')
                ->with('success', "Smart access check-in berhasil untuk booking {$booking->booking_code}.");
        } catch (\Throwable $e) {
            return redirect()->route('proptech.flex.index')
                ->with('error', $e->getMessage());
        }
    }

    public function noShow(
        FlexBooking $booking,
        FlexSpaceService $service
    ): RedirectResponse {
        try {
            $service->handleNoShow($booking);

            return redirect()->route('proptech.flex.index')
                ->with('success', "Booking {$booking->booking_code} ditandai No-Show, deposit disita sebagai pendapatan denda.");
        } catch (\Throwable $e) {
            return redirect()->route('proptech.flex.index')
                ->with('error', $e->getMessage());
        }
    }
}
