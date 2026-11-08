<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ReleaseExpiredRoomBlockAllotmentsAction;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Hotel\Domain\Services\HotelRoomBlockAllotmentManagerService;

final class HotelRoomBlockController extends Controller
{
    public function index(): View
    {
        $allotments = RoomBlockAllotment::with('contract')->latest()->get();

        return view('hotel::room_block_allotment_monitor', [
            'allotments' => $allotments,
        ]);
    }

    public function triggerRelease(Request $request, ReleaseExpiredRoomBlockAllotmentsAction $action): JsonResponse
    {
        $propertyId = $request->input('property_id');
        $certificates = $action->execute($propertyId);

        return response()->json([
            'success' => true,
            'released_blocks_count' => count($certificates),
            'certificates' => array_map(fn ($c) => [
                'block_id' => $c->blockId,
                'tier' => $c->cutoffTier,
                'rooms_released' => $c->roomsReleasedToPublic,
                'remaining' => $c->remainingContractedRooms,
                'signature_hash' => $c->signatureHash,
            ], $certificates),
        ]);
    }

    public function recordPickup(Request $request, HotelRoomBlockAllotmentManagerService $service): JsonResponse
    {
        $validated = $request->validate([
            'block_id' => 'required|exists:htl_room_block_allotments,id',
            'rooms_count' => 'required|integer|min:1',
        ]);

        $allotment = RoomBlockAllotment::findOrFail($validated['block_id']);
        $service->recordPickup($allotment, (int) $validated['rooms_count']);

        return response()->json([
            'success' => true,
            'confirmed_pickup_rooms' => $allotment->fresh()->confirmed_rooms_count,
            'allotted_rooms_count' => $allotment->fresh()->allotted_rooms_count,
        ]);
    }
}
