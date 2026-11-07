<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HotelHousekeepingTask;
use Modules\Hotel\Domain\StateMachines\HousekeepingTaskStateMachine;

final class HotelHousekeepingMobileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $today = CarbonImmutable::now()->format('Y-m-d');

        $tasks = HotelHousekeepingTask::with(['room'])
            ->where(function ($q) use ($user): void {
                if ($user !== null) {
                    $q->where('assigned_user_id', $user->id)
                      ->orWhereNull('assigned_user_id');
                }
            })
            ->where('task_date', $today)
            ->orderByRaw("is_vip DESC, is_priority DESC, route_sequence ASC")
            ->get();

        return view('hotel::housekeeping_mobile', compact('tasks', 'today'));
    }

    public function updateStatus(Request $request, int $taskId): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'inspection_score' => 'nullable|integer|min:0|max:100',
        ]);

        $task = HotelHousekeepingTask::findOrFail($taskId);
        HousekeepingTaskStateMachine::assertCanTransition($task->status, $validated['status']);

        $task->update([
            'status' => $validated['status'],
            'inspection_score' => $validated['inspection_score'] ?? $task->inspection_score,
            'completed_at' => $validated['status'] === 'done' ? CarbonImmutable::now() : $task->completed_at,
            'inspected_at' => in_array($validated['status'], ['inspected', 'failed_inspection'], true) ? CarbonImmutable::now() : $task->inspected_at,
        ]);

        return back()->with('success', "Status tugas kamar #{$task->room_id} diperbarui ke {$validated['status']}.");
    }
}
