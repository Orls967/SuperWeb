<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Services\HotelSpaSchedulingEngine;

class HotelSpaAppointmentController
{
    public function __construct(
        private readonly HotelSpaSchedulingEngine $spaEngine
    ) {}

    public function index(Request $request): View
    {
        return view('hotel::hotel_spa_appointment_board', [
            'today' => CarbonImmutable::now()->toDateString(),
            'activeChambersCount' => 6,
            'totalChambers' => 8,
        ]);
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'treatment_code' => ['required', 'string'],
            'therapist_id' => ['required', 'string'],
            'guest_name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'string'],
            'folio_id' => ['nullable', 'uuid'],
            'is_peak_hour' => ['nullable', 'boolean'],
        ]);

        $booking = $this->spaEngine->bookSpaSession(
            treatmentCode: $validated['treatment_code'],
            therapistId: $validated['therapist_id'],
            guestName: $validated['guest_name'],
            startTimeIso: $validated['start_time'],
            folioId: $validated['folio_id'] ?? null,
            isPeakHour: (bool) ($validated['is_peak_hour'] ?? false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Sesi perawatan spa berhasil dijadwalkan.',
            'booking' => $booking,
        ]);
    }
}
