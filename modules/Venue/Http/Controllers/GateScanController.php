<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateScanLog;

class GateScanController extends Controller
{
    public function index(Request $request): View
    {
        $venues = EntertainmentVenue::all();
        $zones = EntertainmentZone::all();

        $recentScans = GateScanLog::with('ticket')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('venue::gate', [
            'venues' => $venues,
            'zones' => $zones,
            'recentScans' => $recentScans,
        ]);
    }

    public function scan(
        Request $request,
        ScanTicketGateWithAntiPassbackAndAgeCheckAction $scanAction
    ): JsonResponse {
        $validated = $request->validate([
            'ticket_code' => 'required|string',
            'zone_id' => 'required|exists:entertainment_zones,id',
            'direction' => 'required|in:IN,OUT',
            'nonce' => 'required|string|max:64',
            'patron_age' => 'required|integer|min:0|max:120',
        ]);

        $ticket = EntertainmentTicket::where('ticket_number', $validated['ticket_code'])
            ->orWhere('ticket_uuid', $validated['ticket_code'])
            ->first();

        if (! $ticket) {
            return response()->json([
                'status' => 'DENIED',
                'reason' => 'Tiket tidak ditemukan dalam sistem validasi gerbang',
                'age_verified' => false,
            ], 404);
        }

        $zone = EntertainmentZone::findOrFail($validated['zone_id']);
        $userId = (int) (Auth::id() ?? 1);

        $result = $scanAction->execute(
            ticket: $ticket,
            zone: $zone,
            direction: $validated['direction'],
            nonce: $validated['nonce'],
            patronAge: (int) $validated['patron_age'],
            scannedByUserId: $userId
        );

        $minAge = (int) ($zone->venue?->min_age_requirement ?? 21);
        $ageVerified = (int) $validated['patron_age'] >= $minAge;

        return response()->json([
            'status' => $result['status'],
            'reason' => $result['reason'] ?? null,
            'direction' => $validated['direction'],
            'ticket_number' => $ticket->ticket_number,
            'age_verified' => $ageVerified, // boolean yes/no, DOB mentah TIDAK pernah dikirim ke respon/layar
            'zone_name' => $zone->name,
            'scanned_at' => now()->toIso8601String(),
        ]);
    }
}

