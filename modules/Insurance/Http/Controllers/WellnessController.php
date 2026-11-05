<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Insurance\Application\Actions\RecordWellnessActivityAction;
use Modules\Insurance\Domain\Models\WellnessLog;
use Modules\Insurance\Domain\Models\WellnessReward;

final class WellnessController extends Controller
{
    public function dashboard(): View
    {
        $logs = WellnessLog::latest()->take(30)->get();
        $rewards = WellnessReward::latest()->take(10)->get();

        return view('insurance::wellness.dashboard', compact('logs', 'rewards'));
    }

    public function record(Request $request, RecordWellnessActivityAction $action): JsonResponse
    {
        $validated = $request->validate([
            'user_party_id' => 'required|uuid',
            'log_date' => 'required|date',
            'daily_steps' => 'required|integer|min:0',
            'avg_heart_rate_bpm' => 'required|integer|min:30|max:250',
            'sleep_minutes' => 'required|integer|min:0|max:1440',
            'source_device_hash' => 'required|string|max:64',
        ]);

        $result = $action->execute(
            userPartyId: $validated['user_party_id'],
            logDate: $validated['log_date'],
            dailySteps: (int) $validated['daily_steps'],
            avgHeartRateBpm: (int) $validated['avg_heart_rate_bpm'],
            sleepMinutes: (int) $validated['sleep_minutes'],
            deviceHash: $validated['source_device_hash'],
        );

        return response()->json([
            'status' => 'success',
            'message' => $result['anti_gaming_flagged']
                ? 'Aktivitas dicatat namun terdeteksi anomali/gaming flags (reward ditahan).'
                : 'Aktivitas sehat berhasil diverifikasi.',
            'data' => $result,
        ], 201);
    }
}

