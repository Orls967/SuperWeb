<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Domain\Models\HotelHousekeepingLog;
use Modules\Hotel\Domain\Models\HotelRoom;

class HotelHousekeepingController extends Controller
{
    public function index(Request $request)
    {
        $rooms = HotelRoom::with('roomType')
            ->orderBy('room_number')
            ->get();

        $recentLogs = HotelHousekeepingLog::with(['room', 'changedByUser'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'rooms' => $rooms,
                'logs' => $recentLogs,
            ]);
        }

        return view('hotel::hotel.housekeeping', compact('rooms', 'recentLogs'));
    }

    public function updateStatus(Request $request, HotelRoom $room, CheckInGuestAction $action)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:dirty,cleaning,inspected,available,out_of_order',
            'notes' => 'nullable|string|max:255',
        ]);

        $updatedRoom = $action->updateHousekeepingStatus(
            roomId: $room->id,
            targetStatus: $validated['status'],
            changedByUserId: (int) auth()->id(),
            notes: $validated['notes'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'room' => $updatedRoom,
            ]);
        }

        return redirect()->back()->with('success', "Status kamar {$room->room_number} diperbarui menjadi {$validated['status']}.");
    }
}
