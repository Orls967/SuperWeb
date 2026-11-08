<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Actions\AllocateSeasonalSlotsAction;
use Modules\Aviation\Domain\Enums\SlotRequestStatus;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationSlot;
use Modules\Aviation\Domain\Models\AviationSlotRequest;

class AviationSlotController extends Controller
{
    public function __construct(
        private readonly AllocateSeasonalSlotsAction $allocateAction
    ) {}

    public function index(Request $request): View
    {
        $selectedAirportId = $request->query('airport_id');
        $airports = AviationAirport::where('slot_coordinated', true)->get();

        $airport = $selectedAirportId
            ? AviationAirport::find($selectedAirportId)
            : $airports->first();

        $slots = $airport
            ? AviationSlot::where('airport_id', $airport->id)->with('operator')->paginate(20)->withQueryString()
            : collect();

        $requests = $airport
            ? AviationSlotRequest::where('airport_id', $airport->id)->with('operator')->get()
            : collect();

        return view('aviation::slots.index', [
            'airports' => $airports,
            'selectedAirport' => $airport,
            'slots' => $slots,
            'requests' => $requests,
        ]);
    }

    public function requestSlot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'airport_id' => ['required', 'exists:avi_airports,id'],
            'operator_id' => ['required', 'exists:avi_operators,id'],
            'season_code' => ['required', 'string', 'max:16'],
            'requested_time' => ['required', 'integer'],
            'is_new_entrant' => ['boolean'],
        ]);

        $slotReq = AviationSlotRequest::create([
            'airport_id' => $validated['airport_id'],
            'operator_id' => $validated['operator_id'],
            'season_code' => $validated['season_code'],
            'requested_time' => $validated['requested_time'],
            'is_new_entrant' => $validated['is_new_entrant'] ?? false,
            'status' => SlotRequestStatus::SUBMITTED->value,
        ]);

        $slotReq->transitionTo(SlotRequestStatus::SUBMITTED, 'AIRLINE_REQUEST_SUBMITTED');

        return response()->json([
            'message' => 'Permintaan slot berhasil diajukan.',
            'request_id' => $slotReq->id,
        ], 201);
    }
}
